<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use PHPUnit\Framework\TestCase;
use TermTrail\services\RiskScore;

final class RiskScoreTest extends TestCase
{
    private function f(string $sev, bool $verified = true, bool $inferred = false, string $review = 'pending'): array
    {
        return ['title' => $sev, 'severity' => $sev, 'quote_verified' => $verified, 'is_inferred' => $inferred, 'review_status' => $review];
    }

    public function testNoFindingsIsZeroAndLow(): void
    {
        $r = RiskScore::calculate([]);
        $this->assertSame(0, $r['score']);
        $this->assertSame('low', $r['level']);
    }

    public function testSeverityWeights(): void
    {
        $this->assertSame(25, RiskScore::calculate([$this->f('high')])['score']);
        $this->assertSame(10, RiskScore::calculate([$this->f('medium')])['score']);
        $this->assertSame(3, RiskScore::calculate([$this->f('low')])['score']);
    }

    public function testUnverifiedOrInferredCountsAtSixtyPercent(): void
    {
        $this->assertSame(15, RiskScore::calculate([$this->f('high', false)])['score']);
        $this->assertSame(15, RiskScore::calculate([$this->f('high', true, true)])['score']);
        $this->assertSame(6, RiskScore::calculate([$this->f('medium', false, true)])['score']);
    }

    public function testDismissedFindingsAreExcludedButReviewedCount(): void
    {
        $r = RiskScore::calculate([$this->f('high'), $this->f('high', true, false, 'dismissed'), $this->f('medium', true, false, 'reviewed')]);
        $this->assertSame(35, $r['score']);
        $this->assertSame(['high' => 1, 'medium' => 1, 'low' => 0], $r['counts']);
    }

    public function testLevelThresholds(): void
    {
        $this->assertSame('low', RiskScore::calculate([$this->f('high'), $this->f('low')])['level']);          // 28
        $this->assertSame('medium', RiskScore::calculate([$this->f('high'), $this->f('medium')])['level']);    // 35
        $this->assertSame('medium', RiskScore::calculate([$this->f('high'), $this->f('high'), $this->f('medium', false, true)])['level']); // 50+6=56
        $this->assertSame('high', RiskScore::calculate([$this->f('high'), $this->f('high'), $this->f('medium'), $this->f('low')])['level']); // 63
    }

    public function testScoreIsCappedAtOneHundred(): void
    {
        $this->assertSame(100, RiskScore::calculate(array_fill(0, 9, $this->f('high')))['score']);
    }

    public function testUnknownSeverityIsIgnoredAndBreakdownListed(): void
    {
        $r = RiskScore::calculate([$this->f('critical'), $this->f('high')]);
        $this->assertSame(25, $r['score']);
        $this->assertCount(1, $r['breakdown']);
        $this->assertStringContainsString('not a legally validated score', RiskScore::FORMULA);
    }
}
