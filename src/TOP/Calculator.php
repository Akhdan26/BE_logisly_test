<?php

declare(strict_types=1);

namespace App\TOP;

/**
 * Term of Payment (TOP) Calculator
 *
 * Calculates the final Term of Payment for transporter invoices based on
 * baseline TOP and penalties for late POD (Proof of Delivery) submissions.
 *
 * Business Logic:
 * - POD Delay = min(max(0, POD Late Days), 30)
 * - ePOD Delay = min(max(0, ePOD Late Days), 30)
 * - Penalty = POD Delay + ePOD Delay
 * - Total TOP = Baseline TOP + Penalty
 * - Final TOP = min(Total TOP, 45)
 */
final class Calculator
{
    /** Maximum penalty days for physical POD delay */
    public const TS_MAX_TOP_DELAY_POD = 30;

    /** Maximum penalty days for electronic POD delay */
    public const TS_MAX_TOP_DELAY_EPOD = 30;

    /** Absolute maximum TOP result in days */
    public const TS_MAX_TOP_DELAY = 45;

    /** Minimum value for any delay (negative treated as zero) */
    public const MIN_DELAY = 0;

    /**
     * Calculate Term of Payment (TOP) result.
     *
     * @param int $baselineTop  Baseline TOP in days (from transporter contract)
     * @param int $podLateDays  Physical POD late days (negative values treated as 0)
     * @param int $epodLateDays Electronic POD late days (negative values treated as 0)
     *
     * @return int Final TOP result in days, capped at TS_MAX_TOP_DELAY (45)
     *
     * Edge cases handled:
     * - Negative delay values → treated as 0
     * - POD/ePOD delays capped individually at 30 days before summing
     * - Final result capped at 45 days regardless of baseline + penalty
     */
    public function calculate(int $baselineTop, int $podLateDays, int $epodLateDays): int
    {
        // Ensure baseline is never negative
        $baselineTop = max(self::MIN_DELAY, $baselineTop);

        // Step 1: POD Delay — cap negative at 0, then cap max at 30
        $podDelay = min(
            max(self::MIN_DELAY, $podLateDays),
            self::TS_MAX_TOP_DELAY_POD
        );

        // Step 2: ePOD Delay — cap negative at 0, then cap max at 30
        $epodDelay = min(
            max(self::MIN_DELAY, $epodLateDays),
            self::TS_MAX_TOP_DELAY_EPOD
        );

        // Step 3: Penalty = sum of both capped delays
        $penalty = $podDelay + $epodDelay;

        // Step 4: Total TOP = Baseline + Penalty
        $totalTop = $baselineTop + $penalty;

        // Step 5: Final TOP — cap at absolute maximum 45 days
        return min($totalTop, self::TS_MAX_TOP_DELAY);
    }

    /**
     * Calculate with configurable caps (for database-driven configuration).
     *
     * Useful when caps are stored in the database instead of using class constants.
     *
     * @param int $baselineTop    Baseline TOP in days
     * @param int $podLateDays    Physical POD late days
     * @param int $epodLateDays   Electronic POD late days
     * @param int $maxPodDelay    Maximum POD delay cap (configurable)
     * @param int $maxEpodDelay   Maximum ePOD delay cap (configurable)
     * @param int $maxTopResult   Maximum final TOP result (configurable)
     *
     * @return int Final TOP result in days
     */
    public function calculateWithConfig(
        int $baselineTop,
        int $podLateDays,
        int $epodLateDays,
        int $maxPodDelay,
        int $maxEpodDelay,
        int $maxTopResult
    ): int {
        $baselineTop = max(self::MIN_DELAY, $baselineTop);

        $podDelay = min(max(self::MIN_DELAY, $podLateDays), $maxPodDelay);
        $epodDelay = min(max(self::MIN_DELAY, $epodLateDays), $maxEpodDelay);
        $totalTop = $baselineTop + $podDelay + $epodDelay;

        return min($totalTop, $maxTopResult);
    }
}