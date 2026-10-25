<?php
declare(strict_types=1);
namespace TermTrail\services;

final class ContractFileService
{
    /** Stores the upload and tries extraction. Extraction failure is non-fatal. */
    public static function process(array $validated, string $tmp): array
    {
        $stored = FileStorage::storeUpload($tmp, $validated['ext']);
        $r = ['stored' => $stored, 'text_file' => null, 'text' => null, 'error' => null, 'meta' => $validated];
        try {
            $text = TextExtractor::extractFile(FileStorage::contractPath($stored), $validated['ext']);
            $r['text_file'] = FileStorage::saveText($text);
            $r['text'] = $text;
        } catch (ExtractionException $e) {
            $r['error'] = $e->getMessage();
        }
        return $r;
    }

    public static function discard(?array $proc): void
    {
        if (!$proc) { return; }
        FileStorage::deleteContractFile($proc['stored'] ?? null);
        FileStorage::deleteText($proc['text_file'] ?? null);
    }
}
