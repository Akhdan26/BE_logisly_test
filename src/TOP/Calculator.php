<?php

declare(strict_types=1);

namespace App\TOP;

/**
 * Kalkulator Term of Payment (TOP) buat transporter.
 *
 * Logika:
 * - Masing-masing delay (POD & ePOD) di-cap di 30 hari.
 * - Delay negatif dianggap 0.
 * - Total = baseline + POD_delay + ePOD_delay.
 * - Hasil akhir di-cap di 45 hari (biar nggak keterlaluan).
 */
final class Calculator
{
    // Cap per jenis delay (30 hari sesuai requirement)
    public const TS_MAX_TOP_DELAY_POD = 30;
    public const TS_MAX_TOP_DELAY_EPOD = 30;

    // Cap absolut untuk hasil akhir
    public const TS_MAX_TOP_DELAY = 45;

    // Baseline minimum, sekaligus batas bawah delay (negatif → 0)
    public const MIN_DELAY = 0;

    /**
     * Hitung TOP final berdasarkan baseline + denda keterlambatan.
     *
     * @param int $baselineTop  TOP kontrak transporter (hari)
     * @param int $podLateDays  Hari keterlambatan POD fisik
     * @param int $epodLateDays Hari keterlambatan ePOD digital
     */
    public function calculate(int $baselineTop, int $podLateDays, int $epodLateDays): int
    {
        // Baseline nggak boleh negatif
        $baselineTop = max(self::MIN_DELAY, $baselineTop);

        // POD delay: negatif → 0, lalu cap max 30
        $podDelay = min(
            max(self::MIN_DELAY, $podLateDays),
            self::TS_MAX_TOP_DELAY_POD
        );

        // ePOD delay: sama, negatif → 0, cap max 30
        $epodDelay = min(
            max(self::MIN_DELAY, $epodLateDays),
            self::TS_MAX_TOP_DELAY_EPOD
        );

        // Total denda = POD + ePOD (dua pelanggaran terpisah)
        $penalty = $podDelay + $epodDelay;

        // TOP final = baseline + denda
        $totalTop = $baselineTop + $penalty;

        // Cap absolut 45 hari
        return min($totalTop, self::TS_MAX_TOP_DELAY);
    }

    /**
     * Versi configurable — kalau caps disimpen di database/config.
     *
     * Method ini dipake kalau nilai caps nggak hardcoded, tapi
     * diambil dari table config atau service parameter.
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
