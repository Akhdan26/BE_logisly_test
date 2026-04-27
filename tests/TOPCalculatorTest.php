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

    /**
     * @test
     *
     * Required Test Cases from the specification
     * ===========================================
     */

    /**
     * Test Case 1: Basic calculation
     * Baseline 7, POD 5, ePOD 3 → Expected 15
     * Formula: 7 + 5 + 3 = 15, no caps triggered
     */
    public function testCase1BasicCalculation(): void
    {
        $result = $this->calculator->calculate(7, 5, 3);

        $this->assertSame(15, $result);
    }

    /**
     * Test Case 2: POD exceeds individual cap, total exceeds final cap
     * Baseline 10, POD 35, ePOD 25
     * POD capped at 30 → penalty = 30 + 25 = 55 → total = 10 + 55 = 65 → capped at 45
     */
    public function testCase2PodExceedsCapTotalExceedsMax(): void
    {
        $result = $this->calculator->calculate(10, 35, 25);

        $this->assertSame(45, $result);
    }

    /**
     * Test Case 3: Both delays at max, total exceeds final cap
     * Baseline 20, POD 30, ePOD 30
     * penalty = 30 + 30 = 60 → total = 20 + 60 = 80 → capped at 45
     */
    public function testCase3BothDelaysAtMaxTotalExceedsMax(): void
    {
        $result = $this->calculator->calculate(20, 30, 30);

        $this->assertSame(45, $result);
    }

    /**
     * Test Case 4: No penalty, baseline only
     * Baseline 14, POD 0, ePOD 0 → Expected 14
     */
    public function testCase4NoPenaltyBaselineOnly(): void
    {
        $result = $this->calculator->calculate(14, 0, 0);

        $this->assertSame(14, $result);
    }

    /**
     * Test Case 5: Negative POD treated as 0
     * Baseline 5, POD -2, ePOD 0
     * POD treated as 0 → penalty = 0 + 0 = 0 → total = 5
     */
    public function testCase5NegativePodTreatedAsZero(): void
    {
        $result = $this->calculator->calculate(5, -2, 0);

        $this->assertSame(5, $result);
    }

    /**
     * Test Case 6: Total exceeds max cap
     * Baseline 15, POD 20, ePOD 15
     * penalty = 20 + 15 = 35 → total = 15 + 35 = 50 → capped at 45
     */
    public function testCase6TotalExceedsMaxCap(): void
    {
        $result = $this->calculator->calculate(15, 20, 15);

        $this->assertSame(45, $result);
    }

    /**
     * Test Case 7: POD at max, ePOD zero, total under 45
     * Baseline 10, POD 30, ePOD 0
     * penalty = 30 + 0 = 30 → total = 10 + 30 = 40
     */
    public function testCase7PodAtMaxEpodZeroTotalUnderMax(): void
    {
        $result = $this->calculator->calculate(10, 30, 0);

        $this->assertSame(40, $result);
    }

    /**
     * Test Case 8: Baseline at max, no penalty
     * Baseline 45, POD 0, ePOD 0 → Expected 45
     */
    public function testCase8BaselineAtMaxNoPenalty(): void
    {
        $result = $this->calculator->calculate(45, 0, 0);

        $this->assertSame(45, $result);
    }

    /**
     * Additional Edge Cases
     * =====================
     */

    /**
     * Both POD and ePOD negative → treated as 0
     */
    public function testBothDelaysNegative(): void
    {
        $result = $this->calculator->calculate(10, -5, -3);

        $this->assertSame(10, $result);
    }

    /**
     * Negative ePOD, normal POD
     */
    public function testNegativeEpod(): void
    {
        $result = $this->calculator->calculate(10, 5, -3);

        $this->assertSame(15, $result); // 10 + 5 + 0
    }

    /**
     * Both delays exactly at individual cap (30), but total under 45
     * Baseline 0 → penalty 60 → capped at 45
     */
    public function testZeroBaselineMaxPenalties(): void
    {
        $result = $this->calculator->calculate(0, 30, 30);

        $this->assertSame(45, $result); // 0 + 60 → capped at 45
    }

    /**
     * Baseline slightly below 45, small penalty pushes it over
     * Baseline 44, POD 2, ePOD 0 → total 46 → capped at 45
     */
    public function testBaselineNearMaxSmallPenaltyExceeds(): void
    {
        $result = $this->calculator->calculate(44, 2, 0);

        $this->assertSame(45, $result);
    }

    /**
     * POD exactly at individual cap, ePOD under cap
     */
    public function testPodExactlyAtCapEpodUnder(): void
    {
        $result = $this->calculator->calculate(5, 30, 10);

        // penalty = 30 + 10 = 40 → total = 5 + 40 = 45
        $this->assertSame(45, $result);
    }

    /**
     * Very large baseline → still capped at 45
     */
    public function testVeryLargeBaseline(): void
    {
        $result = $this->calculator->calculate(100, 0, 0);

        $this->assertSame(45, $result);
    }

    /**
     * Very large POD and ePOD → capped individually, final capped
     */
    public function testVeryLargeDelays(): void
    {
        $result = $this->calculator->calculate(10, 999, 999);

        // POD capped at 30, ePOD capped at 30 → penalty = 60 → total = 70 → capped at 45
        $this->assertSame(45, $result);
    }

    /**
     * Test configurable caps via calculateWithConfig
     * Scenario: custom caps — max POD 20, max ePOD 20, max total 60
     */
    public function testConfigurableCaps(): void
    {
        // Baseline 10, POD 25, ePOD 25
        // POD capped at 20, ePOD capped at 20 → penalty = 40 → total = 50 → capped at 60 → result = 50
        $result = $this->calculator->calculateWithConfig(10, 25, 25, 20, 20, 60);

        $this->assertSame(50, $result);
    }

    /**
     * Test configurable caps: thresholds higher than defaults
     */
    public function testConfigurableCapsHigherThresholds(): void
    {
        // Baseline 30, POD 40, ePOD 40
        // POD capped at 50, ePOD capped at 50 → penalty = 80 → total = 110 → capped at 90
        $result = $this->calculator->calculateWithConfig(30, 40, 40, 50, 50, 90);

        $this->assertSame(90, $result);
    }

    /**
     * Negative baseline should be treated as 0
     */
    public function testNegativeBaseline(): void
    {
        $result = $this->calculator->calculate(-5, 0, 0);

        $this->assertSame(0, $result);
    }

    /**
     * Zero across the board
     */
    public function testAllZeros(): void
    {
        $result = $this->calculator->calculate(0, 0, 0);

        $this->assertSame(0, $result);
    }

    /**
     * POD at exact cap, ePOD over cap, total under 45
     */
    public function testPodExactEpodOverCapTotalUnderMax(): void
    {
        // Baseline 5, POD 30, ePOD 35 (capped at 30)
        // penalty = 30 + 30 = 60 → total = 5 + 60 = 65 → capped at 45
        $result = $this->calculator->calculate(5, 30, 35);

        $this->assertSame(45, $result);
    }

    /**
     * Verify constants are correct values
     */
    public function testConstants(): void
    {
        $this->assertSame(30, Calculator::TS_MAX_TOP_DELAY_POD);
        $this->assertSame(30, Calculator::TS_MAX_TOP_DELAY_EPOD);
        $this->assertSame(45, Calculator::TS_MAX_TOP_DELAY);
        $this->assertSame(0, Calculator::MIN_DELAY);
    }
}