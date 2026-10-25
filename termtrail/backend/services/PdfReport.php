<?php
declare(strict_types=1);
namespace TermTrail\services;

/** Renders a report dataset as a styled PDF with generation time, filters, page numbers and disclaimers (dompdf). */
final class PdfReport
{
    public const MAX_ROWS = 1500;

    public static function available(): bool { return class_exists(\Dompdf\Dompdf::class); }

    private static function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    public static function html(array $report, array $user, string $generated): string
    {
        $cols = $report['columns'];
        $rows = array_slice($report['rows'], 0, self::MAX_ROWS);
        $cut = count($report['rows']) > self::MAX_ROWS || !empty($report['truncated']);
        $f = [];
        foreach ($report['filters'] as $k => $v) { $f[] = self::h($k) . ': ' . self::h($v); }
        $th = implode('', array_map(fn($c) => '<th>' . self::h($c['label']) . '</th>', $cols));
        $body = '';
        foreach ($rows as $r) {
            $body .= '<tr>' . implode('', array_map(function ($c) use ($r) {
                $v = (string)($r[$c['key']] ?? '');
                if (mb_strlen($v) > 400) { $v = mb_substr($v, 0, 400) . '…'; }
                return '<td>' . self::h($v) . '</td>';
            }, $cols)) . '</tr>';
        }
        if ($body === '') { $body = '<tr><td colspan="' . count($cols) . '" class="empty">No records match this report.</td></tr>'; }
        $font = count($cols) > 8 ? '7px' : '9px';
        return '<!doctype html><html><head><meta charset="utf-8"><style>
            @page { margin: 70px 36px 60px 36px; }
            body { font-family: DejaVu Sans, sans-serif; font-size: ' . $font . '; color: #1b1f3b; }
            .brand { font-size: 11px; color: #5b4bd6; font-weight: bold; letter-spacing: .5px; }
            h1 { font-size: 18px; margin: 2px 0 6px; }
            .meta { color: #55597a; font-size: 8px; margin-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #ece9ff; color: #2d2a6e; text-align: left; padding: 4px; border: 1px solid #cfcaf5; }
            td { padding: 3px 4px; border: 1px solid #e0def5; vertical-align: top; word-wrap: break-word; }
            tr:nth-child(even) td { background: #faf9ff; }
            thead { display: table-header-group; } tr { page-break-inside: avoid; }
            .empty { text-align: center; color: #777; padding: 16px; }
            .note { margin-top: 12px; font-size: 8px; color: #55597a; border-top: 1px solid #ddd; padding-top: 6px; }
            </style></head><body>
            <div class="brand">TermTrail AI</div><h1>' . self::h($report['title']) . '</h1>
            <div class="meta">Generated ' . self::h($generated) . ' (UTC) by ' . self::h($user['name']) . ' · ' . count($rows) . ' record(s)' . ($cut ? ' (list shortened; export CSV for the full data)' : '') .
            ($f ? '<br>Filters: ' . implode(' · ', $f) : '') . '</div>
            <table><thead><tr>' . $th . '</tr></thead><tbody>' . $body . '</tbody></table>
            <div class="note">Informational report generated from TermTrail AI records. AI-generated findings are suggestions that require human review. This document is not legal advice.</div>
            </body></html>';
    }

    public static function render(array $report, array $user): string
    {
        if (!self::available()) { throw new \RuntimeException('PDF export needs the Composer dependencies. Run "composer install" in the project folder.'); }
        $dir = STORAGE_PATH . '/reports/dompdf';
        if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
        $opts = new \Dompdf\Options();
        $opts->set('isRemoteEnabled', false);
        $opts->set('isPhpEnabled', false);
        $opts->set('isHtml5ParserEnabled', true);
        $opts->set('fontDir', $dir);
        $opts->set('fontCache', $dir);
        $opts->set('tempDir', $dir);
        $opts->set('chroot', STORAGE_PATH . '/reports');
        $pdf = new \Dompdf\Dompdf($opts);
        $pdf->loadHtml(self::html($report, $user, gmdate('Y-m-d H:i')));
        $pdf->setPaper('A4', count($report['columns']) > 6 ? 'landscape' : 'portrait');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $w = $canvas->get_width(); $h = $canvas->get_height();
        $canvas->page_text($w - 110, $h - 30, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.35, 0.35, 0.45]);
        $canvas->page_text(36, $h - 30, 'TermTrail AI · informational report · not legal advice', $font, 8, [0.35, 0.35, 0.45]);
        return $pdf->output();
    }
}
