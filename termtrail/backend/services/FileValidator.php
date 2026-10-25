<?php
declare(strict_types=1);
namespace TermTrail\services;

/** Validates an uploaded file by error code, size, extension, real MIME type and content signature. */
final class FileValidator
{
    public const MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    private const MIMES = [
        'pdf'  => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'txt'  => ['text/plain'],
    ];

    private const UPLOAD_ERRORS = [
        UPLOAD_ERR_INI_SIZE => 'The file is larger than the server upload limit.',
        UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server error: temporary folder missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server error: could not write the file.',
        UPLOAD_ERR_EXTENSION => 'The upload was blocked by a server extension.',
    ];

    /** @return array{ext:string,mime:string,size:int,original:string} @throws \InvalidArgumentException */
    public static function validate(array $f, ?int $maxBytes = null): array
    {
        $max = $maxBytes ?? self::MAX_BYTES;
        $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException(self::UPLOAD_ERRORS[$err] ?? 'No file was uploaded.');
        }
        $tmp = (string)($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) { throw new \InvalidArgumentException('Upload failed. Please try again.'); }
        $size = (int)filesize($tmp);
        if ($size <= 0) { throw new \InvalidArgumentException('The file is empty.'); }
        if ($size > $max) { throw new \InvalidArgumentException('The file is larger than ' . round($max / 1048576) . ' MB.'); }

        $original = self::cleanName((string)($f['name'] ?? 'document'));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!isset(self::MIMES[$ext])) { throw new \InvalidArgumentException('Only PDF, DOCX and TXT files are supported.'); }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        if (!in_array($mime, self::MIMES[$ext], true)) {
            throw new \InvalidArgumentException('The file content does not match its ".' . $ext . '" extension.');
        }

        $head = (string)file_get_contents($tmp, false, null, 0, 8192);
        if ($ext === 'pdf' && !str_starts_with($head, '%PDF-')) {
            throw new \InvalidArgumentException('This is not a valid PDF file.');
        }
        if ($ext === 'docx' && !self::looksLikeDocx($tmp)) {
            throw new \InvalidArgumentException('This is not a valid Word (.docx) document.');
        }
        if ($ext === 'txt') {
            $utf16 = str_starts_with($head, "\xFF\xFE") || str_starts_with($head, "\xFE\xFF");
            if (!$utf16 && str_contains($head, "\0")) { throw new \InvalidArgumentException('This does not look like a plain-text file.'); }
        }
        return ['ext' => $ext, 'mime' => $mime, 'size' => $size, 'original' => $original];
    }

    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name);
        if ($name === '') { $name = 'document'; }
        return mb_substr($name, 0, 200);
    }

    private static function looksLikeDocx(string $path): bool
    {
        if (!class_exists(\ZipArchive::class)) { return false; }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) { return false; }
        $ok = $zip->locateName('word/document.xml') !== false && $zip->locateName('[Content_Types].xml') !== false;
        $zip->close();
        return $ok;
    }
}
