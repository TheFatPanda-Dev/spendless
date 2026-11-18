<?php

namespace Tests\Feature\Investments;

use App\Jobs\SyncIbkrConnectionJob;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\InvestmentActivity;
use App\Models\InvestmentPosition;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ibkr\FlexClient;
use App\Services\Ibkr\IbkrSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IbkrIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const SEND = FlexClient::BASE_URL.'/SendRequest*';

    private const GET = FlexClient::BASE_URL.'/GetStatement*';

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
    }

    public function test_connecting_stores_the_token_encrypted_and_starts_a_sync(): void
    {
        Bus::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('investments.ibkr.store'), ['query_id' => '987654', 'token' => '123456789012345678901234'])
            ->assertRedirect();

        $connection = BankConnection::query()->sole();
        $this->assertSame(BankConnection::PROVIDER_IBKR, $connection->provider);
        $this->assertSame('investment', $connection->wallet->type);
        $this->assertSame('987654', $connection->metadata['query_id']);
        $this->assertSame('123456789012345678901234', $connection->credentials_encrypted['token']);
        $this->assertStringNotContainsString(
            '123456789012345678901234',
            (string) DB::table('bank_connections')->value('credentials_encrypted'),
            'The token is encrypted at rest',
        );
        Bus::assertDispatchedAfterResponse(SyncIbkrConnectionJob::class);
    }

    public function test_query_id_and_token_are_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('investments.ibkr.store'), ['query_id' => 'abc', 'token' => 'short'])
            ->assertSessionHasErrors(['query_id', 'token']);
    }

    public function test_sync_downloads_the_report_once_ready_and_stores_everything(): void
    {
        $user = User::factory()->create();
        $dividends = Category::query()->create(['user_id' => $user->id, 'name' => 'Investment Dividends', 'type' => 'income', 'icon' => 'badge-percent', 'color' => 'green']);
        $interest = Category::query()->create(['user_id' => $user->id, 'name' => 'Interest Income', 'type' => 'income', 'icon' => 'piggy-bank', 'color' => 'lime']);
        $connection = $this->connection($user);

        Http::fake([
            self::SEND => Http::response($this->sendSuccess()),
            self::GET => Http::sequence()
                ->push($this->error('Warn', '1019', 'Statement generation in progress. Please try again shortly.'))
                ->push($this->report()),
        ]);

        $run = app(IbkrSync::class)->sync($connection, 'manual');

        $this->assertSame('succeeded', $run->status);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'SendRequest')
            && $request['t'] === 'TOKEN123456789' && $request['q'] === '987654' && $request['v'] === '3');
        Http::assertSentCount(3);

        $account = BankAccount::query()->sole();
        $this->assertSame('EUR', $account->currency);
        $this->assertSame('4567', $account->maskForDisplay());
        $this->assertSame(['current' => 9612.4, 'available' => 512.4, 'as_of' => '2026-11-17'], $account->balances_encrypted);

        $this->assertSame(2, InvestmentPosition::query()->count(), 'Lot rows are not double counted');
        $apple = InvestmentPosition::query()->where('symbol', 'AAPL')->sole();
        $this->assertSame(2305.0, $apple->position_value);
        $this->assertSame(2120.6, $apple->value_in_base);
        $this->assertSame(464.6, $apple->unrealized_pnl_in_base);

        $this->assertSame(['buy', 'deposit'], InvestmentActivity::query()->orderBy('kind')->pluck('kind')->all());
        $buy = InvestmentActivity::query()->where('kind', 'buy')->sole();
        $this->assertSame(-1181.25, $buy->amount);
        $this->assertSame(10.0, $buy->quantity);

        $this->assertSame(3, BankTransaction::query()->count(), 'Dividend, withholding tax and interest; not the deposit');
        $dividend = BankTransaction::query()->where('external_uid', 'ibkr:5001')->sole();
        $this->assertSame('2.50', $dividend->amount);
        $this->assertSame('USD', $dividend->currency);
        $this->assertSame($dividends->id, $dividend->category_id);
        $this->assertSame('-0.38', BankTransaction::query()->where('external_uid', 'ibkr:5002')->sole()->amount);
        $this->assertSame($interest->id, BankTransaction::query()->where('external_uid', 'ibkr:5004')->sole()->category_id);

        $connection->refresh();
        $this->assertSame(BankConnection::STATUS_CONNECTED, $connection->status);
        $this->assertEquals(IbkrSync::nextRun(), $connection->next_sync_at);
    }

    public function test_resync_replaces_holdings_and_does_not_duplicate_history(): void
    {
        $connection = $this->connection(User::factory()->create());
        Http::fake([self::SEND => Http::response($this->sendSuccess()), self::GET => Http::response($this->report())]);

        app(IbkrSync::class)->sync($connection, 'manual');
        app(IbkrSync::class)->sync($connection->fresh(), 'manual');

        $this->assertSame(1, BankAccount::query()->count());
        $this->assertSame(2, InvestmentPosition::query()->count());
        $this->assertSame(2, InvestmentActivity::query()->count());
        $this->assertSame(3, BankTransaction::query()->count());
    }

    public function test_expired_token_asks_for_a_new_one(): void
    {
        $connection = $this->connection(User::factory()->create());
        Http::fake([self::SEND => Http::response($this->error('Fail', '1012', 'Token has expired.'))]);

        $run = app(IbkrSync::class)->sync($connection, 'scheduled');

        $this->assertSame('failed', $run->status);
        $connection->refresh();
        $this->assertSame(BankConnection::STATUS_NEEDS_REAUTH, $connection->status);
        $this->assertNull($connection->next_sync_at);
        $this->assertStringContainsString('expired', $connection->last_sync_error);
    }

    public function test_report_still_generating_is_retried_later(): void
    {
        $connection = $this->connection(User::factory()->create());
        Http::fake([
            self::SEND => Http::response($this->sendSuccess()),
            self::GET => Http::response($this->error('Warn', '1019', 'Statement generation in progress.')),
        ]);

        app(IbkrSync::class)->sync($connection, 'scheduled');

        $connection->refresh();
        $this->assertSame(BankConnection::STATUS_SYNC_FAILED, $connection->status);
        $this->assertTrue($connection->next_sync_at->isBefore(now()->addHour()));
    }

    public function test_updating_the_token_resumes_syncing(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $connection = $this->connection($user, BankConnection::STATUS_NEEDS_REAUTH);

        $this->actingAs($user)
            ->patch(route('investments.ibkr.update', $connection), ['query_id' => '111222', 'token' => 'NEWTOKEN1234567890'])
            ->assertRedirect();

        $connection->refresh();
        $this->assertSame('NEWTOKEN1234567890', $connection->credentials_encrypted['token']);
        $this->assertSame('111222', $connection->metadata['query_id']);
        $this->assertSame(BankConnection::STATUS_SYNCING, $connection->status);
        Bus::assertDispatchedAfterResponse(SyncIbkrConnectionJob::class);

        $this->actingAs(User::factory()->create())
            ->patch(route('investments.ibkr.update', $connection), ['query_id' => '1', 'token' => 'X'])
            ->assertForbidden();
    }

    public function test_sync_now_includes_ibkr(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $connection = $this->connection($user);

        $this->actingAs($user)->postJson(route('bank-connections.refresh', $connection))->assertJson(['queued' => 1]);

        Bus::assertDispatchedAfterResponse(SyncIbkrConnectionJob::class, fn (SyncIbkrConnectionJob $job): bool => $job->trigger === 'manual');
        $this->assertSame(BankConnection::STATUS_SYNCING, $connection->fresh()->status);
    }

    public function test_the_daily_schedule_includes_ibkr(): void
    {
        Bus::fake();
        $connection = $this->connection(User::factory()->create());
        $connection->forceFill(['next_sync_at' => now()->subMinute()])->save();
        $notDue = $this->connection(User::factory()->create());
        $notDue->forceFill(['next_sync_at' => now()->addHour()])->save();

        $this->artisan('banking:dispatch-due-syncs')->assertSuccessful();

        Bus::assertDispatched(SyncIbkrConnectionJob::class, 1);
        Bus::assertDispatched(SyncIbkrConnectionJob::class, fn (SyncIbkrConnectionJob $job): bool => $job->bankConnectionId === $connection->id
            && $job->trigger === 'scheduled');
    }

    public function test_wallet_page_shows_holdings_and_never_the_token(): void
    {
        $user = User::factory()->create();
        $connection = $this->connection($user);
        Http::fake([self::SEND => Http::response($this->sendSuccess()), self::GET => Http::response($this->report())]);
        app(IbkrSync::class)->sync($connection, 'manual');

        $response = $this->actingAs($user)->get(route('wallets.show', $connection->wallet_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wallets/show')
                ->where('wallet.connections.0.provider', 'ibkr')
                ->where('wallet.connections.0.investments.total_value', 9612.4)
                ->has('wallet.connections.0.investments.holdings', 2)
                ->has('wallet.connections.0.investments.activities', 2));

        $this->assertStringNotContainsString('TOKEN123456789', (string) $response->getContent());
    }

    private function connection(User $user, string $status = BankConnection::STATUS_CONNECTED): BankConnection
    {
        $wallet = Wallet::query()->create(['user_id' => $user->id, 'name' => 'Interactive Brokers', 'type' => 'investment', 'currency' => 'EUR']);

        return BankConnection::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'provider' => BankConnection::PROVIDER_IBKR,
            'institution_name' => 'Interactive Brokers',
            'aspsp_name' => 'Interactive Brokers',
            'aspsp_country' => 'ZZ',
            'state' => 'state-'.uniqid(),
            'status' => $status,
            'metadata' => ['query_id' => '987654'],
            'credentials_encrypted' => ['token' => 'TOKEN123456789'],
        ])->load('user', 'wallet');
    }

    private function sendSuccess(): string
    {
        return '<FlexStatementResponse timestamp="18 November, 2026 07:05 AM EST"><Status>Success</Status>'
            .'<ReferenceCode>1234567890</ReferenceCode>'
            .'<Url>'.FlexClient::BASE_URL.'/GetStatement</Url></FlexStatementResponse>';
    }

    private function error(string $status, string $code, string $message): string
    {
        return '<FlexStatementResponse timestamp="18 November, 2026 07:05 AM EST"><Status>'.$status.'</Status>'
            .'<ErrorCode>'.$code.'</ErrorCode><ErrorMessage>'.$message.'</ErrorMessage></FlexStatementResponse>';
    }

    private function report(): string
    {
        return <<<'XML'
<FlexQueryResponse queryName="SpendLess" type="AF">
<FlexStatements count="1">
<FlexStatement accountId="U1234567" fromDate="20251118" toDate="20261117" period="Last365CalendarDays" whenGenerated="20261118;070501">
<AccountInformation accountId="U1234567" currency="EUR" name="Jane Doe" accountType="Individual" />
<EquitySummaryInBase>
<EquitySummaryByReportDateInBase accountId="U1234567" currency="EUR" reportDate="20261116" cash="500.00" stock="9000.00" total="9500.00" />
<EquitySummaryByReportDateInBase accountId="U1234567" currency="EUR" reportDate="20261117" cash="512.40" stock="9100.00" total="9612.40" />
</EquitySummaryInBase>
<OpenPositions>
<OpenPosition accountId="U1234567" currency="USD" fxRateToBase="0.92" assetCategory="STK" symbol="AAPL" description="APPLE INC" conid="265598" reportDate="20261117" position="10" markPrice="230.50" positionValue="2305" costBasisMoney="1800" fifoPnlUnrealized="505" levelOfDetail="SUMMARY" />
<OpenPosition accountId="U1234567" currency="USD" fxRateToBase="0.92" assetCategory="STK" symbol="AAPL" description="APPLE INC" conid="265598" reportDate="20261117" position="10" markPrice="230.50" positionValue="2305" costBasisMoney="1800" fifoPnlUnrealized="505" levelOfDetail="LOT" />
<OpenPosition accountId="U1234567" currency="EUR" fxRateToBase="1" assetCategory="STK" symbol="VWCE" description="VANGUARD FTSE ALL-WORLD" conid="128831206" reportDate="20261117" position="60" markPrice="120.10" positionValue="7206" costBasisMoney="6500" fifoPnlUnrealized="706" levelOfDetail="SUMMARY" />
</OpenPositions>
<Trades>
<Trade accountId="U1234567" currency="EUR" fxRateToBase="1" assetCategory="STK" symbol="VWCE" description="VANGUARD FTSE ALL-WORLD" tradeID="111" transactionID="9001" tradeDate="20261003" dateTime="20261003;093000" quantity="10" tradePrice="118.00" proceeds="-1180" ibCommission="-1.25" netCash="-1181.25" buySell="BUY" levelOfDetail="EXECUTION" />
<Trade accountId="U1234567" currency="EUR" symbol="VWCE" tradeID="111" tradeDate="20261003" quantity="10" buySell="BUY" levelOfDetail="ORDER" />
</Trades>
<CashTransactions>
<CashTransaction accountId="U1234567" currency="USD" fxRateToBase="0.92" assetCategory="STK" symbol="AAPL" description="AAPL(US0378331005) CASH DIVIDEND USD 0.25 PER SHARE (Ordinary Dividend)" dateTime="20261114;202000" amount="2.50" type="Dividends" transactionID="5001" levelOfDetail="DETAIL" />
<CashTransaction accountId="U1234567" currency="USD" fxRateToBase="0.92" assetCategory="STK" symbol="AAPL" description="AAPL(US0378331005) CASH DIVIDEND USD 0.25 PER SHARE - US TAX" dateTime="20261114;202000" amount="-0.38" type="Withholding Tax" transactionID="5002" levelOfDetail="DETAIL" />
<CashTransaction accountId="U1234567" currency="EUR" fxRateToBase="1" description="CASH RECEIPTS / ELECTRONIC FUND TRANSFERS" dateTime="20261001" amount="1000" type="Deposits/Withdrawals" transactionID="5003" levelOfDetail="DETAIL" />
<CashTransaction accountId="U1234567" currency="EUR" fxRateToBase="1" description="EUR CREDIT INT FOR OCT-2026" dateTime="20261103" amount="1.10" type="Broker Interest Received" transactionID="5004" levelOfDetail="DETAIL" />
</CashTransactions>
<CashReport>
<CashReportCurrency accountId="U1234567" currency="BASE_SUMMARY" endingCash="512.40" />
</CashReport>
</FlexStatement>
</FlexStatements>
</FlexQueryResponse>
XML;
    }
}
