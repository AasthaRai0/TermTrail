<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use PHPUnit\Framework\TestCase;
use TermTrail\helpers\Validator;
use TermTrail\services\{EvidenceVerifier, RegexExtractor, TextChunker, TextExtractor};

final class ParsingTest extends TestCase
{
    private const TEXT = "This Agreement is effective as of 1 January 2026 and shall commence on 2026-02-01. The term expires on 31/12/2027.\nThe Client shall pay Rs. 1,25,000 per month and a one-time fee of USD 5,000.50. A deposit of 20,000 INR is due.\nEither party may terminate on thirty (30) days written notice. Renewal date: March 5, 2027. Call 12 345 678. Penalty of 2% applies.";

    private function dates(string $text): array
    {
        $out = [];
        foreach (RegexExtractor::extract($text)['date_suggestions'] as $d) { $out[$d['field']] = $d; }
        return $out;
    }

    public function testDatesInManyFormats(): void
    {
        $d = $this->dates(self::TEXT);
        $this->assertSame('2026-01-01', $d['effective_date']['value']);
        $this->assertSame('2026-02-01', $d['start_date']['value']);
        $this->assertSame('2027-12-31', $d['expiry_date']['value']);
        $this->assertSame('2027-03-05', $d['renewal_date']['value']);
        $this->assertSame('30', $d['notice_period_days']['value']);
    }

    public function testEveryExtractedSnippetExistsInTheSourceText(): void
    {
        $norm = EvidenceVerifier::normalize(self::TEXT);
        $r = RegexExtractor::extract(self::TEXT);
        foreach (array_merge($r['date_suggestions'], $r['payments']) as $x) { $this->assertTrue(EvidenceVerifier::verify($x['quote'], $norm), $x['quote']); }
    }

    public function testAmountsAndCurrencies(): void
    {
        $got = array_map(fn($p) => $p['currency'] . $p['amount'], RegexExtractor::extract(self::TEXT)['payments']);
        sort($got);
        $this->assertSame(['INR125000.00', 'INR20000.00', 'USD5000.50'], $got);
    }

    public function testNothingIsInventedFromUnrelatedText(): void
    {
        $r = RegexExtractor::extract('Meeting on 31/02/2026 about it. Call 12 345 678. Discount of 20%.');
        $this->assertSame([], $r['date_suggestions'], 'impossible dates are ignored');
        $this->assertSame([], $r['payments']);
    }

    public function testAmbiguousNumericDatesAreFlagged(): void
    {
        $d = $this->dates('This agreement is effective from 03/04/2026.')['effective_date'];
        $this->assertSame('2026-04-03', $d['value']);
        $this->assertNotEmpty($d['note']);
        $this->assertEmpty($this->dates('This agreement is effective from 25/04/2026.')['effective_date']['note']);
    }

    public function testAmountAndDateValidators(): void
    {
        $this->assertSame('1250.50', Validator::amount('1,250.50'));
        $this->assertSame('0', Validator::amount('0', true));
        foreach (['0', '-5', '1.234', 'abc', '', '1e5', '99999999999999'] as $bad) { $this->assertNull(Validator::amount($bad), $bad); }
        $this->assertTrue(Validator::date('2028-02-29'));
        foreach (['2027-02-29', '2026-00-10', '10-01-2026', '', 'today'] as $bad) { $this->assertFalse(Validator::date($bad), $bad); }
    }

    public function testTextNormalisation(): void
    {
        $n = TextExtractor::normalize("  A\r\n\r\n\r\n\r\nB \t C\0D\u{00A0}E\x07  ");
        $this->assertSame("A\n\nB CD E", $n);
        $this->assertStringNotContainsString("\0", $n);
        $this->assertStringNotContainsString("\r", $n);
        $this->assertSame('caf' . "\u{00E9}", TextExtractor::normalize("caf\xE9"), 'Windows-1252 text is converted to UTF-8');
    }

    public function testChunkingOverlapAndSize(): void
    {
        $words = array_map(fn($i) => "w$i", range(1, 1200));
        $chunks = TextChunker::chunk(implode(' ', $words), 500, 50);
        $this->assertCount(3, $chunks);
        $this->assertSame(500, count(explode(' ', $chunks[0])));
        $this->assertSame('w451', explode(' ', $chunks[1])[0], '50-word overlap');
        $this->assertStringEndsWith('w1200', $chunks[2]);
        $this->assertSame([], TextChunker::chunk('   '));
        $this->assertCount(1, TextChunker::chunk('short text'));
    }
}
