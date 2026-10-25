<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use PHPUnit\Framework\TestCase;
use TermTrail\services\{InlineDiff, VersionDiffService as D, VersionException};

final class VersionDiffTest extends TestCase
{
    public function testIdenticalTextsHaveNoDifferences(): void
    {
        $r = D::diff(['a', 'b', 'c'], ['a', 'b', 'c']);
        $this->assertTrue($r['identical']);
        $this->assertSame([], $r['hunks']);
        $this->assertSame(0, $r['stats']['added_lines'] + $r['stats']['removed_lines']);
    }

    public function testAddedAndRemovedParagraphs(): void
    {
        $r = D::diff(['one', 'two', 'three'], ['one', 'three', 'four']);
        $this->assertFalse($r['identical']);
        $types = [];
        foreach ($r['hunks'] as $h) { foreach ($h['lines'] as $l) { $types[$l['t']][] = $l['s'] ?? ''; } }
        $this->assertSame(['two'], $types['del']);
        $this->assertSame(['four'], $types['ins']);
        $this->assertContains('one', $types['eq']);
        $this->assertSame([1, 1], [$r['stats']['added_lines'], $r['stats']['removed_lines']]);
    }

    public function testReplacementIsPairedWithWordLevelHighlights(): void
    {
        $r = D::diff(['Pay within thirty (30) days.'], ['Pay within forty-five (45) days.']);
        $chg = $r['hunks'][0]['lines'][0];
        $this->assertSame('chg', $chg['t']);
        $oldDel = implode('', array_column(array_filter($chg['old'], fn($s) => $s['t'] === 'del'), 's'));
        $newIns = implode('', array_column(array_filter($chg['new'], fn($s) => $s['t'] === 'ins'), 's'));
        $this->assertStringContainsString('thirty', $oldDel);
        $this->assertStringContainsString('forty-five', $newIns);
    }

    public function testWordCounts(): void
    {
        $r = D::diff(['alpha beta gamma'], ['alpha beta gamma delta epsilon']);
        $this->assertSame(5, $r['stats']['added_words']);
        $this->assertSame(3, $r['stats']['removed_words']);
    }

    public function testUnchangedRunsAreCollapsedWithCounts(): void
    {
        $old = array_map(fn($i) => "para $i", range(1, 30));
        $new = $old; $new[2] = 'changed A'; $new[25] = 'changed B';
        $r = D::diff($old, $new);
        $this->assertCount(2, $r['hunks']);
        $this->assertSame(0, $r['hunks'][0]['gap_before']);
        $this->assertGreaterThan(10, $r['hunks'][1]['gap_before'], 'the long unchanged middle is not shown');
        $this->assertGreaterThanOrEqual(0, $r['gap_after']);
    }

    public function testSensitiveAreasAreFlaggedFromChangedParagraphsOnly(): void
    {
        $old = ['Payment is due in 30 days.', 'Either party may terminate on 30 days notice.', 'The liability of the provider is capped.', 'Headings are for convenience.'];
        $new = ['Payment is due in 45 days.', 'Either party may terminate on 30 days notice.', 'The liability of the provider is unlimited.', 'Headings are for reference.'];
        $areas = array_column(D::diff($old, $new)['flags'], 'area');
        sort($areas);
        $this->assertSame(['liability', 'payment'], $areas, 'unchanged termination clause is not flagged');
    }

    public function testNothingIsFlaggedForHarmlessEdits(): void
    {
        $this->assertSame([], D::diff(['Headings are for convenience.'], ['Headings are for reference.'])['flags']);
    }

    public function testRefusesAbsurdlyLargeInputs(): void
    {
        $this->expectException(VersionException::class);
        D::diff(array_fill(0, D::MAX_LINES + 1, 'x'), ['y']);
    }

    public function testVersionWithoutTextGivesAnInformativeError(): void
    {
        $this->expectException(VersionException::class);
        $this->expectExceptionMessage('Version 3 has no extracted text');
        D::textOf(['version_number' => 3, 'extracted_text_path' => null]);
    }

    public function testLinesHelperDropsBlankLinesAndTrims(): void
    {
        $this->assertSame(['a', 'b'], D::lines("  a \r\n\r\n\n b\n"));
    }

    public function testInlineDiffMarksOnlyChangedWords(): void
    {
        [$o, $n] = InlineDiff::words('the quick brown fox', 'the slow brown fox');
        $this->assertSame([['eq', 'the '], ['del', 'quick'], ['eq', ' brown fox']], array_map(fn($s) => [$s['t'], $s['s']], $o));
        $this->assertSame([['eq', 'the '], ['ins', 'slow'], ['eq', ' brown fox']], array_map(fn($s) => [$s['t'], $s['s']], $n));
        [$o2] = InlineDiff::words(str_repeat('a ', 400), 'b');
        $this->assertSame('del', $o2[0]['t'], 'very long lines fall back to whole-line marking');
    }
}
