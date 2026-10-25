<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use PHPUnit\Framework\TestCase;
use TermTrail\services\CsvExporter;

final class CsvExporterTest extends TestCase
{
    public function testFormulaStartersAreNeutralised(): void
    {
        foreach (['=1+1', '=HYPERLINK("http://x","y")', '+cmd|calc', '-2+3', '@SUM(A1)', "\t=1", "\r=1", '  =1+1', '- 5 + 2'] as $v) {
            $this->assertStringStartsWith("'", CsvExporter::cell($v), json_encode($v));
        }
    }

    public function testNormalTextAndNumbersAreUntouched(): void
    {
        foreach (['Acme Ltd', 'INR 5,000', '2026-12-31', '', '5000.50', '-5', '12', 'Say =hello'] as $v) { $this->assertSame($v, CsvExporter::cell($v)); }
        $this->assertSame('12', CsvExporter::cell(12));
        $this->assertSame('-3.5', CsvExporter::cell(-3.5));
        $this->assertSame('', CsvExporter::cell(null));
        $this->assertSame('Yes', CsvExporter::cell(true));
    }

    public function testBuildAddsBomQuotesAndEscapesEveryCell(): void
    {
        $csv = CsvExporter::build(['columns' => [['key' => 'a', 'label' => 'Title'], ['key' => 'b', 'label' => '=Evil header']],
            'rows' => [['a' => 'He said "hi", ok', 'b' => '=1+1'], ['a' => "line1\nline2", 'b' => null]]]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $rows = array_map('str_getcsv', explode("\n", trim(substr($csv, 3)), 2));
        $this->assertSame(["Title", "'=Evil header"], str_getcsv(explode("\n", substr($csv, 3))[0]));
        $this->assertStringContainsString('"He said ""hi"", ok",\'=1+1', $csv);
        $this->assertStringContainsString("\"line1\nline2\"", $csv);
    }
}
