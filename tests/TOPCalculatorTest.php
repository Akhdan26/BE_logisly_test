<?php

declare(strict_types=1);

namespace Tests;

use App\TOP\Calculator;
use PHPUnit\Framework\TestCase;

final class TOPCalculatorTest extends TestCase
{
    private Calculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new Calculator();
    }

    // Required test cases dari soal
    // ===========================================

    // Case 1: basic — blom nyentuh cap manapun
    // 7 + 5 + 3 = 15
    public function testCase1BasicCalculation(): void
    {
        $result = $this->calculator->calculate(7, 5, 3);
        $this->assertSame(15, $result);
    }

    // Case 2: POD kelewat cap 30, total juga kelewat 45
    // POD 35 → cap 30, ePOD 25, baseline 10 → total 65 → final cap 45
    public function testCase2PodExceedsCapTotalExceedsMax(): void
    {
        $result = $this->calculator->calculate(10, 35, 25);
        $this->assertSame(45, $result);
    }

    // Case 3: dua2nya di max, total jebol
    // 20 + 30 + 30 = 80 → final cap 45
    public function testCase3BothDelaysAtMaxTotalExceedsMax(): void
    {
        $result = $this->calculator->calculate(20, 30, 30);
        $this->assertSame(45, $result);
    }

    // Case 4: nggak ada penalty, baseline doang
    // 14 + 0 + 0 = 14
    public function testCase4NoPenaltyBaselineOnly(): void
    {
        $result = $this->calculator->calculate(14, 0, 0);
        $this->assertSame(14, $result);
    }

    // Case 5: POD negatif → dianggap 0
    // 5 + 0 + 0 = 5
    public function testCase5NegativePodTreatedAsZero(): void
    {
        $result = $this->calculator->calculate(5, -2, 0);
        $this->assertSame(5, $result);
    }

    // Case 6: total melebihi cap akhir
    // 15 + 20 + 15 = 50 → final cap 45
    public function testCase6TotalExceedsMaxCap(): void
    {
        $result = $this->calculator->calculate(15, 20, 15);
        $this->assertSame(45, $result);
    }

    // Case 7: POD max tapi ePOD nol, total masih di bawah 45
    // 10 + 30 + 0 = 40
    public function testCase7PodAtMaxEpodZeroTotalUnderMax(): void
    {
        $result = $this->calculator->calculate(10, 30, 0);
        $this->assertSame(40, $result);
    }

    // Case 8: baseline udah di max, nggak ada penalty
    // 45 + 0 + 0 = 45
    public function testCase8BaselineAtMaxNoPenalty(): void
    {
        $result = $this->calculator->calculate(45, 0, 0);
        $this->assertSame(45, $result);
    }

    // Additional edge cases
    // =====================

    // Delay negatif dua2nya → dianggap 0
    public function testBothDelaysNegative(): void
    {
        $result = $this->calculator->calculate(10, -5, -3);
        $this->assertSame(10, $result);
    }

    // Cuma ePOD yang negatif
    public function testNegativeEpod(): void
    {
        $result = $this->calculator->calculate(10, 5, -3);
        $this->assertSame(15, $result); // 10 + 5 + 0
    }

    // Baseline 0, tapi dua2 delay max → final cap 45
    public function testZeroBaselineMaxPenalties(): void
    {
        $result = $this->calculator->calculate(0, 30, 30);
        $this->assertSame(45, $result); // 0 + 60 → final cap 45
    }

    // Baseline deket max, penalty kecil nge-push ke atas 45
    // 44 + 2 = 46 → final cap 45
    public function testBaselineNearMaxSmallPenaltyExceeds(): void
    {
        $result = $this->calculator->calculate(44, 2, 0);
        $this->assertSame(45, $result);
    }

    // POD pas di cap, ePOD di bawah cap
    public function testPodExactlyAtCapEpodUnder(): void
    {
        $result = $this->calculator->calculate(5, 30, 10);
        // 5 + 30 + 10 = 45
        $this->assertSame(45, $result);
    }

    // Baseline gede banget (100) → tetep final cap 45
    public function testVeryLargeBaseline(): void
    {
        $result = $this->calculator->calculate(100, 0, 0);
        $this->assertSame(45, $result);
    }

    // Delay gede banget (999) → di-cap 30 dulu, baru final cap 45
    public function testVeryLargeDelays(): void
    {
        $result = $this->calculator->calculate(10, 999, 999);
        // POD cap 30, ePOD cap 30 → 10 + 60 = 70 → final cap 45
        $this->assertSame(45, $result);
    }

    // Configurable caps: custom max POD 20, ePOD 20, total 60
    public function testConfigurableCaps(): void
    {
        // 10 + 20 + 20 = 50, masih di bawah final cap 60
        $result = $this->calculator->calculateWithConfig(10, 25, 25, 20, 20, 60);
        $this->assertSame(50, $result);
    }

    // Configurable caps: thresholds lebih gede dari default
    public function testConfigurableCapsHigherThresholds(): void
    {
        // 30 + 40 + 40 = 110, final cap 90
        $result = $this->calculator->calculateWithConfig(30, 40, 40, 50, 50, 90);
        $this->assertSame(90, $result);
    }

    // Baseline negatif → dianggap 0
    public function testNegativeBaseline(): void
    {
        $result = $this->calculator->calculate(-5, 0, 0);
        $this->assertSame(0, $result);
    }

    // Semua nol
    public function testAllZeros(): void
    {
        $result = $this->calculator->calculate(0, 0, 0);
        $this->assertSame(0, $result);
    }

    // POD pas di cap, ePOD kelewat cap
    public function testPodExactEpodOverCapTotalUnderMax(): void
    {
        // 5 + 30 + 30 = 65 → final cap 45
        $result = $this->calculator->calculate(5, 30, 35);
        $this->assertSame(45, $result);
    }

    // Verifikasi nilai constant
    public function testConstants(): void
    {
        $this->assertSame(30, Calculator::TS_MAX_TOP_DELAY_POD);
        $this->assertSame(30, Calculator::TS_MAX_TOP_DELAY_EPOD);
        $this->assertSame(45, Calculator::TS_MAX_TOP_DELAY);
        $this->assertSame(0, Calculator::MIN_DELAY);
    }
}