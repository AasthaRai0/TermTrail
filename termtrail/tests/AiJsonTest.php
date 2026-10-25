<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use PHPUnit\Framework\TestCase;
use TermTrail\services\{AiException, AiJson};

final class AiJsonTest extends TestCase
{
    public function testParsesPlainFencedAndWrappedJson(): void
    {
        $this->assertSame(1, AiJson::parse('{"a":1}')['a']);
        $this->assertSame(2, AiJson::parse("```json\n{\"a\":2}\n```")['a']);
        $this->assertSame(3, AiJson::parse('Here you go: {"a":3} thanks')['a']);
    }

    public function testRejectsNonJsonAndNonObjects(): void
    {
        foreach (['', 'not json', '[1,2,3]', '"text"', '42'] as $bad) {
            try { AiJson::parse($bad); $this->fail("should reject: $bad"); } catch (AiException $e) { $this->assertStringContainsString('not valid JSON', $e->getMessage()); }
        }
    }

    public function testSectionNormalisesEnumsAndDropsJunk(): void
    {
        $s = AiJson::section([
            'clauses' => [['type' => 'Payment Terms', 'summary' => 'Net 30', 'quote' => 'q', 'confidence' => 0.9], ['type' => 'nope', 'summary' => 'x'], ['summary' => ''], 'junk', 42],
            'risks' => [['title' => 'R', 'description' => 'd', 'severity' => 'CRITICAL', 'kind' => 'odd'], ['title' => '', 'description' => 'd'], ['title' => 'T']],
        ]);
        $this->assertSame('payment_terms', $s['clauses'][0]['type']);
        $this->assertSame('other', $s['clauses'][1]['type']);
        $this->assertCount(2, $s['clauses']);
        $this->assertSame('low', $s['risks'][0]['severity'], 'unknown severity falls back to the least alarming value');
        $this->assertSame('inferred', $s['risks'][0]['kind']);
        $this->assertCount(1, $s['risks'], 'rows missing title/description are dropped');
    }

    public function testDatesAmountsAndCurrenciesAreValidated(): void
    {
        $s = AiJson::section([
            'obligations' => [['title' => 'O', 'due_date' => '2026-02-30', 'recurrence' => 'daily'], ['title' => 'P', 'due_date' => '2026-03-15', 'recurrence' => 'monthly']],
            'payments' => [['amount' => '1,000', 'currency' => 'xxx'], ['amount' => 5000.5, 'currency' => 'inr', 'title' => 'Fee'], ['amount' => -5], ['amount' => 'lots']],
            'dates' => [['field' => 'expiry_date', 'value' => '2027-01-01'], ['field' => 'expiry_date', 'value' => '01/01/2027'], ['field' => 'hack', 'value' => '2027-01-01'], ['field' => 'notice_period_days', 'value' => '60'], ['field' => 'notice_period_days', 'value' => '99999']],
        ]);
        $this->assertNull($s['obligations'][0]['due_date']);
        $this->assertSame('none', $s['obligations'][0]['recurrence']);
        $this->assertSame('2026-03-15', $s['obligations'][1]['due_date']);
        $this->assertSame('monthly', $s['obligations'][1]['recurrence']);
        $this->assertCount(2, $s['payments']);
        $this->assertNull($s['payments'][0]['currency']);
        $this->assertSame('1000.00', $s['payments'][0]['amount']);
        $this->assertSame('INR', $s['payments'][1]['currency']);
        $this->assertSame('5000.50', $s['payments'][1]['amount']);
        $this->assertSame(['expiry_date', 'notice_period_days'], array_column($s['dates'], 'field'));
    }

    public function testListsAreCappedAndStringsClipped(): void
    {
        $this->assertCount(15, AiJson::section(['clauses' => array_fill(0, 50, ['summary' => 'x'])])['clauses']);
        $s = AiJson::summary(['executive_summary' => str_repeat('a', 5000), 'parties' => ['A', 'B', ['x'], '']]);
        $this->assertSame(1500, mb_strlen($s['executive_summary']));
        $this->assertSame(['A', 'B'], $s['parties']);
    }

    public function testSummaryRequiresAnExecutiveSummary(): void
    {
        $this->expectException(AiException::class);
        AiJson::summary(['detailed_explanation' => 'x']);
    }

    public function testControlCharactersAreStripped(): void
    {
        $s = AiJson::section(['clauses' => [['summary' => "Pay\x00 now\x07!"]]]);
        $this->assertSame('Pay now!', $s['clauses'][0]['summary']);
    }

    public function testAnswerAndVersionSummaryShapes(): void
    {
        $a = AiJson::answer(['answer' => 'Yes', 'sufficient' => false, 'evidence' => [['passage' => 'p2', 'quote' => 'q'], 'junk']]);
        $this->assertFalse($a['sufficient']);
        $this->assertSame('P2', $a['evidence'][0]['passage']);
        $v = AiJson::versionSummary(['summary' => 'Changed', 'changes' => [['area' => 'bogus', 'change' => 'x', 'impact' => 'weird']]]);
        $this->assertSame(['other', 'unclear'], [$v['changes'][0]['area'], $v['changes'][0]['impact']]);
    }
}
