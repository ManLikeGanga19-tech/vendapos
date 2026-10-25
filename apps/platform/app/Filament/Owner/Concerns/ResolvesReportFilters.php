<?php

declare(strict_types=1);

namespace App\Filament\Owner\Concerns;

use App\Domain\Analytics\Actions\SummariseSalesByDay;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Turns Owner report filter state into the business days and branches to
 * report on, for report pages and the widgets above them. The state comes
 * from the browser, so the branch only ever narrows the person's own
 * branches and a bad range falls back to today.
 */
trait ResolvesReportFilters
{
    use ScopesToStaffBranches;

    /**
     * The business days the filters ask for, oldest first.
     *
     * @param  array<string, mixed>|null  $filters
     * @return array{0: string, 1: string}
     */
    protected function rangeFrom(?array $filters): array
    {
        $today = CarbonImmutable::parse($this->businessToday());
        $period = $filters['period'] ?? [];
        $lastMonth = $today->startOfMonth()->subMonth();

        [$from, $to] = match ($period['period'] ?? 'today') {
            'yesterday' => [$today->subDay(), $today->subDay()],
            'this_week' => [$today->startOfWeek(CarbonImmutable::MONDAY), $today],
            'last_7_days' => [$today->subDays(6), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$lastMonth, $lastMonth->endOfMonth()->startOfDay()],
            'custom' => [$this->dateOrNull($period['from'] ?? null) ?? $today, $this->dateOrNull($period['until'] ?? null) ?? $today],
            default => [$today, $today],
        };

        $to = $to->gt($today) ? $today : $to;

        if ($from->gt($to) || $from->diffInDays($to) + 1 > SummariseSalesByDay::MAX_DAYS) {
            return [$today->toDateString(), $today->toDateString()];
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return list<string>
     */
    protected function reportBranchIds(?array $filters): array
    {
        $picked = $filters['branch']['value'] ?? null;

        return is_string($picked) && in_array($picked, $this->visibleBranchIds(), true)
            ? [$picked]
            : $this->visibleBranchIds();
    }

    // Day and month names follow the app's language (Laravel keeps Carbon's locale in step).
    protected function dayLabel(string $date): string
    {
        return CarbonImmutable::parse($date)->translatedFormat('D j M Y');
    }

    /** "Fri 23 Oct 2026", "17–23 Oct 2026", "28 Sep – 4 Oct 2026" or "29 Dec 2025 – 4 Jan 2026". */
    protected function rangeLabel(string $from, string $to): string
    {
        $a = CarbonImmutable::parse($from);
        $b = CarbonImmutable::parse($to);

        return match (true) {
            $a->isSameDay($b) => $this->dayLabel($from),
            $a->isSameMonth($b) => $a->translatedFormat('j').'–'.$b->translatedFormat('j M Y'),
            $a->isSameYear($b) => $a->translatedFormat('j M').' – '.$b->translatedFormat('j M Y'),
            default => $a->translatedFormat('j M Y').' – '.$b->translatedFormat('j M Y'),
        };
    }

    /** A real calendar date in Y-m-d form (a time after it is ignored), or null for anything else. */
    protected function dateOrNull(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}/', $value) !== 1) {
            return null;
        }

        $ymd = substr($value, 0, 10);

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $ymd);
        } catch (InvalidFormatException) {
            return null;
        }

        // Rejects dates PHP would roll over, such as 2026-02-31.
        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $ymd ? $date : null;
    }
}
