<?php
declare(strict_types=1);
namespace TermTrail\services;

/** Private file storage. Names are random, never user-supplied; lookups are pattern-checked (no path traversal). */
final class FileStorage
{
    private const CONTRACT_RE = '/^[a-f0-9]{32}\.(pdf|docx|txt)$/';
    private const TEXT_RE = '/^[a-f0-9]{32}\.txt$/';

    private static function dir(string $sub): string
    {
        $d = STORAGE_PATH . '/' . $sub;
        if (!is_dir($d) && !mkdir($d, 0750, true) && !is_dir($d)) { throw new \RuntimeException('Storage directory is not writable: ' . $sub); }
        return $d;
    }

    public static function storeUpload(string $tmp, string $ext): string
    {
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = self::dir('contracts') . '/' . $name;
        $ok = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $dest) : (defined('TT_TESTING') && copy($tmp, $dest));
        if (!$ok) { throw new \RuntimeException('Could not save the uploaded file. Check storage folder permissions.'); }
        @chmod($dest, 0640);
        return $name;
    }

    public static function contractPath(string $name): string
    {
        if (!preg_match(self::CONTRACT_RE, $name)) { throw new \RuntimeException('Invalid stored file name.'); }
        return self::dir('contracts') . '/' . $name;
    }

    public static function saveText(string $text): string
    {
        $name = bin2hex(random_bytes(16)) . '.txt';
        if (file_put_contents(self::dir('extracted_text') . '/' . $name, $text, LOCK_EX) === false) {
            throw new \RuntimeException('Could not save extracted text.');
        }
        return $name;
    }

    public static function textPath(string $name): string
    {
        if (!preg_match(self::TEXT_RE, $name)) { throw new \RuntimeException('Invalid text file name.'); }
        return self::dir('extracted_text') . '/' . $name;
    }

    public static function deleteContractFile(?string $name): void
    {
        if ($name && preg_match(self::CONTRACT_RE, $name)) { @unlink(self::dir('contracts') . '/' . $name); }
    }

    public static function deleteText(?string $name): void
    {
        if ($name && preg_match(self::TEXT_RE, $name)) { @unlink(self::dir('extracted_text') . '/' . $name); }
    }
}
