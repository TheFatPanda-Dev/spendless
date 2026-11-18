<?php

namespace App\Services\Assets;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A fixed-term savings product (e.g. a fixed-rate Cash ISA) whose profit is paid at the end:
 * profit = amount × rate × years. Before the end, profit is shown as earned day by day.
 */
final class FixedTermSavings
{
    public function __construct(
        public readonly float $amount,
        public readonly float $ratePercent,
        public readonly CarbonImmutable $startDate,
        public readonly float $years,
    ) {}

    /**
     * @param  array<string, mixed>  $terms  As stored on the connection: amount, rate, start_date, years.
     */
    public static function fromTerms(array $terms): ?self
    {
        if (! isset($terms['amount'], $terms['rate'], $terms['start_date'], $terms['years'])) {
            return null;
        }

        return new self(
            (float) $terms['amount'],
            (float) $terms['rate'],
            CarbonImmutable::parse((string) $terms['start_date'])->startOfDay(),
            (float) $terms['years'],
        );
    }

    public function maturityDate(): CarbonImmutable
    {
        $wholeYears = (int) floor($this->years);
        $extraMonths = (int) round(($this->years - $wholeYears) * 12);

        return $this->startDate->addYears($wholeYears)->addMonths($extraMonths);
    }

    public function totalProfit(): float
    {
        return round($this->amount * $this->ratePercent / 100 * $this->years, 2);
    }

    public function profitEarned(?CarbonInterface $on = null): float
    {
        return round($this->totalProfit() * $this->progress($on), 2);
    }

    public function valueOn(?CarbonInterface $on = null): float
    {
        return round($this->amount + $this->profitEarned($on), 2);
    }

    public function valueAtMaturity(): float
    {
        return round($this->amount + $this->totalProfit(), 2);
    }

    public function hasMatured(?CarbonInterface $on = null): bool
    {
        return CarbonImmutable::instance($on ?? now())->startOfDay()->greaterThanOrEqualTo($this->maturityDate());
    }

    /**
     * Share of the term that has passed, 0..1.
     */
    public function progress(?CarbonInterface $on = null): float
    {
        $on = CarbonImmutable::instance($on ?? now())->startOfDay();
        $totalDays = max(1, $this->startDate->diffInDays($this->maturityDate()));
        $elapsedDays = max(0, $this->startDate->diffInDays($on, false));

        return min(1.0, $elapsedDays / $totalDays);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'rate' => $this->ratePercent,
            'years' => $this->years,
            'start_date' => $this->startDate->toDateString(),
            'maturity_date' => $this->maturityDate()->toDateString(),
            'total_profit' => $this->totalProfit(),
            'profit_earned' => $this->profitEarned(),
            'value_today' => $this->valueOn(),
            'value_at_maturity' => $this->valueAtMaturity(),
            'progress' => round($this->progress(), 4),
            'matured' => $this->hasMatured(),
        ];
    }
}
