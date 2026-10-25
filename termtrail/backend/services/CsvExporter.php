<?php
declare(strict_types=1);
namespace TermTrail\services;

final class CsvExporter
{
    /** Neutralise spreadsheet formula injection: text starting with = + - @ (or tab/CR, even after spaces) gets a leading apostrophe. Plain numbers are left alone. */
    public static function cell(mixed $v): string
    {
        if ($v === null) { return ''; }
        if (is_bool($v)) { return $v ? 'Yes' : 'No'; }
        if (is_int($v) || is_float($v)) { return (string)$v; }
        $s = (string)$v;
        if ($s === '' || preg_match('/^-?\d+(?:\.\d+)?$/', $s)) { return $s; }
        if (preg_match('/^[\s]*[=+\-@\t\r]/', $s) || preg_match('/^[\x00-\x1F]/', $s)) { return "'" . $s; }
        return $s;
    }

    public static function build(array $report): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents/₹ correctly
        fputcsv($fh, array_map([self::class, 'cell'], array_column($report['columns'], 'label')));
        foreach ($report['rows'] as $row) {
            fputcsv($fh, array_map(fn($c) => self::cell($row[$c['key']] ?? null), $report['columns']));
        }
        rewind($fh);
        $out = (string)stream_get_contents($fh);
        fclose($fh);
        return $out;
    }
}
