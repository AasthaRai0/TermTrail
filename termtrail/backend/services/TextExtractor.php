<?php
declare(strict_types=1);
namespace TermTrail\services;

final class TextExtractor
{
    public const MAX_CHARS = 1000000;
    private const MAX_XML_BYTES = 30 * 1024 * 1024;

    /** @throws ExtractionException */
    public static function extractFile(string $path, string $ext): string
    {
        $raw = match ($ext) {
            'txt'  => self::fromTxt($path),
            'docx' => self::fromDocx($path),
            'pdf'  => self::fromPdf($path),
            default => throw new ExtractionException('Unsupported file type.'),
        };
        $text = self::normalize($raw);
        if (mb_strlen($text) < 20) {
            throw new ExtractionException($ext === 'pdf'
                ? 'No selectable text was found. This looks like a scanned or image-based PDF. OCR is not supported in this version.'
                : 'No readable text was found in this document.');
        }
        if (mb_strlen($text) > self::MAX_CHARS) { $text = mb_substr($text, 0, self::MAX_CHARS); }
        return $text;
    }

    public static function normalize(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) { $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252'); }
        $text = str_replace(["\0", "\u{FEFF}", "\u{00A0}"], ['', '', ' '], $text);
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? '';          // drop control chars except newline/tab
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? '';
        $text = preg_replace('/ ?\n ?/u', "\n", $text) ?? '';
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? '';
        return trim($text);
    }

    private static function fromTxt(string $path): string
    {
        $data = (string)file_get_contents($path);
        if (str_starts_with($data, "\xFF\xFE")) { return mb_convert_encoding(substr($data, 2), 'UTF-8', 'UTF-16LE'); }
        if (str_starts_with($data, "\xFE\xFF")) { return mb_convert_encoding(substr($data, 2), 'UTF-8', 'UTF-16BE'); }
        return $data;
    }

    private static function fromDocx(string $path): string
    {
        if (!class_exists(\ZipArchive::class)) { throw new ExtractionException('The PHP "zip" extension is not enabled. Enable it in php.ini.'); }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) { throw new ExtractionException('The DOCX file could not be opened.'); }
        $stat = $zip->statName('word/document.xml');
        if ($stat === false) { $zip->close(); throw new ExtractionException('The DOCX file has no readable body.'); }
        if ($stat['size'] > self::MAX_XML_BYTES) { $zip->close(); throw new ExtractionException('The DOCX file is too large to process.'); }
        $xml = (string)$zip->getFromName('word/document.xml');
        $zip->close();
        $xml = preg_replace(['/<w:tab\b[^>]*\/>/', '/<w:br\b[^>]*\/>/', '/<\/w:p>/'], ["\t", "\n", "\n"], $xml) ?? '';
        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function fromPdf(string $path): string
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new ExtractionException('PDF support is not installed. Run "composer install" in the project folder.');
        }
        try {
            $pdf = (new \Smalot\PdfParser\Parser())->parseFile($path);
            return $pdf->getText();
        } catch (\Throwable $e) {
            error_log('PDF parse failed: ' . $e->getMessage());
            throw new ExtractionException('The PDF could not be read. It may be encrypted, corrupted, or image-based (OCR is not supported).');
        }
    }
}
