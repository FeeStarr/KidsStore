<?php

namespace App\Helpers;

use Carbon\Carbon;

class BusinessDayHelper
{
    /**
     * Add business days to a date (skipping weekends).
     */
    public static function addBusinessDays(Carbon $date, int $days): Carbon
    {
        $result = $date->copy()->startOfDay();
        $added = 0;
        while ($added < $days) {
            $result->addDay();
            if ($result->isWeekday()) {
                $added++;
            }
        }
        return $result;
    }

    /**
     * Count business days between two dates (excluding start, including end).
     */
    public static function businessDaysBetween(Carbon $from, Carbon $to): int
    {
        $days = 0;
        $current = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($current->lt($end)) {
            $current->addDay();
            if ($current->isWeekday()) {
                $days++;
            }
        }
        return $days;
    }

    /**
     * Calculate the SLA deadline for a given start date and business day limit.
     */
    public static function slaDeadline(Carbon $start, int $businessDays): Carbon
    {
        return self::addBusinessDays($start, $businessDays);
    }
}
