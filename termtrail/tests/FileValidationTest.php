<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use PHPUnit\Framework\TestCase;
use TermTrail\services\FileValidator;

final class FileValidationTest extends TestCase
{
    private function file(string $content, string $name, int $err = UPLOAD_ERR_OK): array
    {
        $p = sys_get_temp_dir() . '/tt_' . bin2hex(random_bytes(6));
        file_put_contents($p, $content);
        register_shutdown_function(fn() => @unlink($p));
        return ['name' => $name, 'tmp_name' => $p, 'error' => $err, 'size' => strlen($content)];
    }

    private function docx(bool $withBody = true): string
    {
        $p = sys_get_temp_dir() . '/tt_' . bin2hex(random_bytes(6)) . '.zip';
        $z = new \ZipArchive(); $z->open($p, \ZipArchive::CREATE);
        $z->addFromString('[Content_Types].xml', '<Types/>');
        if ($withBody) { $z->addFromString('word/document.xml', '<w:document/>'); } else { $z->addFromString('hello.txt', 'hi'); }
        $z->close();
        $b = (string)file_get_contents($p); @unlink($p);
        return $b;
    }

    private function assertRejected(array $f, string $needle, ?int $max = null): void
    {
        try { FileValidator::validate($f, $max); $this->fail('expected rejection'); }
        catch (\InvalidArgumentException $e) { $this->assertStringContainsString($needle, $e->getMessage()); }
    }

    public function testAcceptsPlainText(): void
    {
        $r = FileValidator::validate($this->file("Hello contract text\n", 'My Contract.TXT'));
        $this->assertSame('txt', $r['ext']);
        $this->assertSame('text/plain', $r['mime']);
        $this->assertSame('My Contract.TXT', $r['original']);
    }

    public function testAcceptsRealPdfHeaderAndDocxPackage(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
        $this->assertSame('pdf', FileValidator::validate($this->file($pdf, 'a.pdf'))['ext']);
        $this->assertSame('docx', FileValidator::validate($this->file($this->docx(), 'a.docx'))['ext']);
    }

    public function testRejectsDisallowedExtensions(): void
    {
        foreach (['x.php', 'x.exe', 'x.html', 'x.jpg', 'x', 'x.txt.php', 'x.phtml'] as $n) { $this->assertRejected($this->file('hello world', $n), 'Only PDF, DOCX and TXT'); }
    }

    public function testRejectsContentThatDoesNotMatchTheExtension(): void
    {
        $this->assertRejected($this->file("MZ\x90\x00 binary", 'x.pdf'), 'does not match');
        $this->assertRejected($this->file("<?php echo 1; ?>", 'x.pdf'), 'does not match');
        $this->assertRejected($this->file('just text', 'x.docx'), 'does not match');
    }

    public function testRejectsBrokenDocx(): void
    {
        $this->assertRejected($this->file($this->docx(false), 'x.docx'), 'not a valid Word');
    }

    public function testRejectsBinaryMasqueradingAsText(): void
    {
        $this->expectException(\InvalidArgumentException::class); // rejected either by the MIME check or the NUL-byte check
        FileValidator::validate($this->file("ab\0cd\0\0\0 binary", 'x.txt'));
    }

    public function testRejectsEmptyAndOversize(): void
    {
        $this->assertRejected($this->file('', 'x.txt'), 'empty');
        $this->assertRejected($this->file(str_repeat('a', 2000), 'x.txt'), 'larger than', 1000);
    }

    public function testRejectsUploadErrorsAndMissingFile(): void
    {
        $this->assertRejected($this->file('x', 'x.txt', UPLOAD_ERR_INI_SIZE), 'server upload limit');
        $this->assertRejected($this->file('x', 'x.txt', UPLOAD_ERR_PARTIAL), 'partially');
        $this->assertRejected([], 'No file');
    }

    public function testClientSuppliedNamesAreSanitisedForDisplay(): void
    {
        $this->assertSame('passwd', FileValidator::cleanName('../../etc/passwd'));
        $this->assertSame('evil.txt', FileValidator::cleanName('C:\\Windows\\evil.txt'));
        $this->assertSame('ab.txt', FileValidator::cleanName("a\x00b\x1f.txt"));
        $this->assertSame('document', FileValidator::cleanName('   '));
        $this->assertSame(200, mb_strlen(FileValidator::cleanName(str_repeat('n', 400) . '.txt')));
    }
}
