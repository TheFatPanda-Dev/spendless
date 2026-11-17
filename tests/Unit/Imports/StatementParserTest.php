<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\ImportValueParser;
use App\Services\Imports\ParsedTransaction;
use App\Services\Imports\StatementParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatementParserTest extends TestCase
{
    /**
     * @return array<string, array{string, float|null}>
     */
    public static function amounts(): array
    {
        return [
            'plain negative' => ['-12.34', -12.34],
            'pound with thousands' => ['£1,234.56', 1234.56],
            'pound negative' => ['£-3.10', -3.10],
            'trailing minus' => ['12.34-', -12.34],
            'brackets' => ['(45.00)', -45.00],
            'european' => ['1.234,56 €', 1234.56],
            'decimal comma' => ['12,50', 12.50],
            'thousands comma only' => ['1,250', 1250.0],
            'debit suffix' => ['12.00 DR', -12.00],
            'credit suffix' => ['12.00 CR', 12.00],
            'currency code' => ['-45.20 GBP', -45.20],
            'empty' => ['', null],
            'text' => ['n/a', null],
        ];
    }

    #[DataProvider('amounts')]
    public function test_amounts(string $input, ?float $expected): void
    {
        $this->assertSame($expected, ImportValueParser::amount($input));
    }

    public function test_dates(): void
    {
        $this->assertSame('2026-10-01', ImportValueParser::date('01/10/2026')['date']);
        $this->assertSame('2026-10-01', ImportValueParser::date('01 Oct 2026')['date']);
        $this->assertSame('2026-10-01', ImportValueParser::date('2026-10-01 09:15:02')['date']);
        $this->assertSame('2026-10-01 09:15:02', ImportValueParser::date('2026-10-01 09:15:02')['datetime']);
        $this->assertSame('2026-10-01', ImportValueParser::date('20261001')['date']);
        $this->assertSame('2026-10-01', ImportValueParser::date('46296')['date'], 'Excel serial date');
        $this->assertNull(ImportValueParser::date('not a date'));
        $this->assertSame('d/m/Y', ImportValueParser::detectDateFormat(['01/10/2026', '13/10/2026']));
    }

    public function test_revolut_csv(): void
    {
        $statement = StatementParser::parse($this->file(<<<'CSV'
Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance
TOPUP,Current,2026-10-01 07:00:00,2026-10-01 07:00:01,Payment from John,1000.00,0.00,GBP,COMPLETED,1000.00
CARD_PAYMENT,Current,2026-10-01 09:15:02,2026-10-02 08:00:11,Tesco,-12.40,0.00,GBP,COMPLETED,987.60
EXCHANGE,Current,2026-10-02 10:00:00,2026-10-02 10:00:00,Exchanged to EUR,-50.00,0.25,GBP,COMPLETED,937.35
TRANSFER,Savings,2026-10-02 11:00:00,2026-10-02 11:00:00,To pocket,-1.00,0.00,GBP,COMPLETED,10.00
CARD_PAYMENT,Current,2026-10-03 18:20:00,,Pret A Manger,-4.50,0.00,GBP,PENDING,
CARD_PAYMENT,Current,2026-10-02 12:00:00,,Cancelled order,-9.99,0.00,GBP,REVERTED,
CSV, 'csv'));

        $this->assertSame('revolut', $statement->format);
        $this->assertSame('Revolut', $statement->bank);
        $this->assertSame('GBP', $statement->currency);
        $this->assertCount(5, $statement->transactions, 'The savings product row is left out');
        $this->assertNotEmpty($statement->warnings);

        $byDescription = $this->byDescription($statement->transactions);
        $this->assertSame(-50.25, $byDescription['Exchanged to EUR']->amount, 'Fee is added to the amount');
        $this->assertTrue($byDescription['Pret A Manger']->pending);
        $this->assertTrue($byDescription['Cancelled order']->cancelled);
        $this->assertSame(937.35, $statement->closingBalance, 'Latest completed running balance');
        $this->assertSame('2026-10-02', $statement->balanceDate);
    }

    public function test_barclays_csv(): void
    {
        $statement = StatementParser::parse($this->file(<<<'CSV'
Number,Date,Account,Amount,Subcategory,Memo
,01/10/2026,20-00-00 12345678,-23.10,PAYMENT,SAINSBURYS S/MKT    ON 30 SEP          BCC
,02/10/2026,20-00-00 12345678,2500.00,DIRECTDEP,ACME LTD SALARY
CSV, 'csv'));

        $this->assertSame('barclays', $statement->format);
        $this->assertCount(2, $statement->transactions);
        $this->assertSame('SAINSBURYS S/MKT ON 30 SEP BCC', $statement->transactions[0]->description);
        $this->assertSame(-23.10, $statement->transactions[0]->amount);
        $this->assertSame('2026-10-01', $statement->transactions[0]->date);
        $this->assertNull($statement->closingBalance);
    }

    public function test_nationwide_csv_in_windows_1252(): void
    {
        $csv = <<<'CSV'
"Account Name:","FlexDirect ****12345"
"Account Balance:","£3,231.46"
"Available Balance: ","£3,231.46"

"Date","Transaction type","Description","Paid out","Paid in","Balance"
"01 Oct 2026","Contactless Payment","TFL TRAVEL CH","£3.10","","£1,231.46"
"02 Oct 2026","Bank credit","ACME LTD","","£2,000.00","£3,231.46"
CSV;

        $statement = StatementParser::parse($this->file(mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'), 'csv'));

        $this->assertSame('nationwide', $statement->format);
        $this->assertSame('FlexDirect ****12345', $statement->accountHint);
        $this->assertSame(-3.10, $statement->transactions[0]->amount);
        $this->assertSame(2000.0, $statement->transactions[1]->amount);
        $this->assertSame(3231.46, $statement->closingBalance);
    }

    public function test_nationwide_credit_card_csv(): void
    {
        $statement = StatementParser::parse($this->file(<<<'CSV'
"Date","Transactions","Location","Paid out","Paid in"
"03 Oct 2026","AMAZON MARKETPLACE","LUXEMBOURG","£25.99",""
"10 Oct 2026","PAYMENT RECEIVED","","","£100.00"
CSV, 'csv'));

        $this->assertSame('nationwide_card', $statement->format);
        $this->assertSame(-25.99, $statement->transactions[0]->amount);
        $this->assertSame('AMAZON MARKETPLACE · LUXEMBOURG', $statement->transactions[0]->description);
        $this->assertSame(100.0, $statement->transactions[1]->amount);
    }

    public function test_ofx_sgml_credit_card(): void
    {
        $statement = StatementParser::parse($this->file(<<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII

<OFX>
<SIGNONMSGSRSV1><SONRS><STATUS><CODE>0<SEVERITY>INFO</STATUS><DTSERVER>20261017</SONRS></SIGNONMSGSRSV1>
<CREDITCARDMSGSRSV1><CCSTMTTRNRS><TRNUID>1<STATUS><CODE>0<SEVERITY>INFO</STATUS>
<CCSTMTRS><CURDEF>GBP<CCACCTFROM><ACCTID>4321123412341234</CCACCTFROM>
<BANKTRANLIST><DTSTART>20261001<DTEND>20261017
<STMTTRN><TRNTYPE>DEBIT<DTPOSTED>20261005<TRNAMT>-45.20<FITID>2026100501<NAME>WAITROSE 123<MEMO>LONDON</STMTTRN>
<STMTTRN><TRNTYPE>CREDIT<DTPOSTED>20261012000000.000[0:GMT]<TRNAMT>200.00<FITID>2026101201<NAME>PAYMENT THANK YOU</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL><BALAMT>-845.20<DTASOF>20261017</LEDGERBAL>
</CCSTMTRS></CCSTMTTRNRS></CREDITCARDMSGSRSV1>
</OFX>
OFX, 'ofx'));

        $this->assertSame('ofx', $statement->format);
        $this->assertSame('GBP', $statement->currency);
        $this->assertSame('Card ending 1234', $statement->accountHint);
        $this->assertCount(2, $statement->transactions);
        $this->assertSame('WAITROSE 123 LONDON', $statement->transactions[0]->description);
        $this->assertSame('2026100501', $statement->transactions[0]->externalId);
        $this->assertSame(200.0, $statement->transactions[1]->amount);
        $this->assertSame('2026-10-12', $statement->transactions[1]->date);
        $this->assertSame(-845.20, $statement->closingBalance);
        $this->assertSame('2026-10-17', $statement->balanceDate);
    }

    public function test_ofx_xml(): void
    {
        $statement = StatementParser::parse($this->file(<<<'OFX'
<?xml version="1.0" encoding="UTF-8"?>
<?OFX OFXHEADER="200" VERSION="211" SECURITY="NONE" OLDFILEUID="NONE" NEWFILEUID="NONE"?>
<OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><CURDEF>GBP</CURDEF>
<BANKACCTFROM><BANKID>200000</BANKID><ACCTID>12345678</ACCTID><ACCTTYPE>CHECKING</ACCTTYPE></BANKACCTFROM>
<BANKTRANLIST>
<STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20261003</DTPOSTED><TRNAMT>-9.99</TRNAMT><FITID>A1</FITID><NAME>NETFLIX.COM</NAME></STMTTRN>
</BANKTRANLIST>
<LEDGERBAL><BALAMT>1500.00</BALAMT><DTASOF>20261017</DTASOF></LEDGERBAL>
<AVAILBAL><BALAMT>1450.00</BALAMT><DTASOF>20261017</DTASOF></AVAILBAL>
</STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>
OFX, 'ofx'));

        $this->assertCount(1, $statement->transactions);
        $this->assertSame('Account ending 5678', $statement->accountHint);
        $this->assertSame(1500.0, $statement->closingBalance);
        $this->assertSame(1450.0, $statement->availableBalance);
    }

    public function test_santander_text_download(): void
    {
        $nbsp = "\u{00A0}";
        $text = "From:{$nbsp}01/10/2026 to 17/10/2026\n\nAccount:{$nbsp}XXXX XXXX XXXX 1234\n\n"
            ."Date:{$nbsp}05/10/2026\nDescription:{$nbsp}CARD PAYMENT TO WAITROSE,45.20 GBP\nAmount:{$nbsp}-45.20 GBP\nBalance:{$nbsp}-845.20 GBP\n\n"
            ."Date:{$nbsp}12/10/2026\nDescription:{$nbsp}PAYMENT RECEIVED - THANK YOU\nAmount:{$nbsp}200.00 GBP\nBalance:{$nbsp}-645.20 GBP\n";

        $statement = StatementParser::parse($this->file($text, 'txt'));

        $this->assertSame('santander_txt', $statement->format);
        $this->assertSame('Account ending 1234', $statement->accountHint);
        $this->assertCount(2, $statement->transactions);
        $this->assertSame(-45.20, $statement->transactions[0]->amount);
        $this->assertSame(-645.20, $statement->closingBalance);
        $this->assertSame('2026-10-12', $statement->balanceDate);
    }

    public function test_unknown_csv_is_guessed_and_can_be_mapped(): void
    {
        $path = $this->file(<<<'CSV'
Posting Date;Details;Money Out;Money In;Running Balance
01.10.2026;Coffee shop;2,50;;97,50
02.10.2026;Refund;;10,00;107,50
CSV, 'csv');

        $guessed = StatementParser::parse($path);
        $this->assertSame('csv', $guessed->format);
        $this->assertFalse($guessed->needsMapping, 'Columns are guessed from their names');
        $this->assertSame(-2.50, $guessed->transactions[0]->amount);
        $this->assertSame(107.50, $guessed->closingBalance);

        $flipped = StatementParser::parse($path, [...$guessed->mapping, 'flip_sign' => true]);
        $this->assertSame(2.50, $flipped->transactions[0]->amount);
    }

    public function test_csv_without_recognisable_columns_needs_mapping(): void
    {
        $statement = StatementParser::parse($this->file("a,b,c\n01/10/2026,x,5\n", 'csv'));

        $this->assertTrue($statement->needsMapping);
        $this->assertSame(['a', 'b', 'c'], $statement->columns);
        $this->assertSame([['01/10/2026', 'x', '5']], $statement->sampleRows);
    }

    public function test_xlsx(): void
    {
        $path = $this->xlsx([
            ['Type', 'Product', 'Started Date', 'Completed Date', 'Description', 'Amount', 'Fee', 'Currency', 'State', 'Balance'],
            ['CARD_PAYMENT', 'Current', '2026-10-01 09:15:02', '2026-10-02 08:00:11', 'Tesco', '-12.40', '0', 'GBP', 'COMPLETED', '987.60'],
        ]);

        $statement = StatementParser::parse($path);

        $this->assertSame('revolut', $statement->format);
        $this->assertSame(-12.40, $statement->transactions[0]->amount);
    }

    private function file(string $contents, string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'stmt').'.'.$extension;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'stmt').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $sheetRows = '';

        foreach ($rows as $r => $row) {
            $cells = '';

            foreach ($row as $c => $value) {
                $ref = chr(65 + $c).($r + 1);
                $cells .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars($value).'</t></is></c>';
            }

            $sheetRows .= '<row r="'.($r + 1).'">'.$cells.'</row>';
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }

    /**
     * @param  list<ParsedTransaction>  $transactions
     * @return array<string, ParsedTransaction>
     */
    private function byDescription(array $transactions): array
    {
        $map = [];

        foreach ($transactions as $transaction) {
            $map[$transaction->description] = $transaction;
        }

        return $map;
    }
}
