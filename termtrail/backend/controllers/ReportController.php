<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\helpers\{Audit, Response};
use TermTrail\middleware\Auth;
use TermTrail\services\{CsvExporter, PdfReport, ReportService};

final class ReportController
{
    public static function types(): never
    {
        $u = Auth::requireApi();
        $out = [];
        foreach (ReportService::TYPES as $k => $label) { if (ReportService::allowed($k, $u)) { $out[] = ['type' => $k, 'label' => $label]; } }
        Response::ok(['types' => $out, 'pdf_available' => PdfReport::available()]);
    }

    private static function build(array $u): array
    {
        $type = (string)($_GET['type'] ?? '');
        if (!isset(ReportService::TYPES[$type])) { Response::error('Unknown report type.', 404); }
        if (!ReportService::allowed($type, $u)) { Response::error('You do not have permission to run this report.', 403); }
        return ReportService::build($type, $_GET, $u);
    }

    public static function data(): never
    {
        $u = Auth::requireApi();
        $r = self::build($u);
        $total = count($r['rows']);
        Response::ok(['title' => $r['title'], 'columns' => $r['columns'], 'rows' => array_slice($r['rows'], 0, 50), 'total' => $total, 'truncated' => $r['truncated'], 'filters' => $r['filters']]);
    }

    public static function export(): never
    {
        $u = Auth::requireApi();
        $fmt = (string)($_GET['format'] ?? 'csv');
        if (!in_array($fmt, ['csv', 'pdf'], true)) { Response::error('Unsupported format.', 422); }
        $r = self::build($u);
        $name = 'termtrail-' . $r['type'] . '-' . date('Ymd-His');
        if ($fmt === 'pdf') {
            try { $bytes = PdfReport::render($r, $u); }
            catch (\RuntimeException $e) { Response::error($e->getMessage(), 501); }
            catch (\Throwable $e) { error_log('PDF render failed: ' . $e->getMessage()); Response::error('The PDF could not be generated. See the server log for details.', 500); }
            header('Content-Type: application/pdf');
        } else {
            $bytes = CsvExporter::build($r);
            header('Content-Type: text/csv; charset=utf-8');
        }
        Audit::log((int)$u['id'], 'report.export', null, null, ['type' => $r['type'], 'format' => $fmt, 'rows' => count($r['rows']), 'filters' => $r['filters']]);
        header('Content-Disposition: attachment; filename="' . $name . '.' . $fmt . '"');
        header('Content-Length: ' . strlen($bytes));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        echo $bytes;
        exit;
    }
}
