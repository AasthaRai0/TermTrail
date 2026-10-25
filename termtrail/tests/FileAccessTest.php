<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use TermTrail\models\ContractModel;
use TermTrail\services\{FileStorage, VersionService};

final class FileAccessTest extends DbTestCase
{
    public function testStoredNamesAreRandomAndPathTraversalIsImpossible(): void
    {
        $good = str_repeat('a1', 16) . '.pdf';
        $this->assertStringEndsWith('/storage/contracts/' . $good, FileStorage::contractPath($good));
        foreach (['../../.env', '..\\..\\.env', '/etc/passwd', 'C:\\Windows\\win.ini', 'abc.pdf', $good . '.php', str_repeat('A1', 16) . '.pdf', str_repeat('a1', 16) . '.exe', "$good\0.txt", '', '../' . $good, './' . $good] as $bad) {
            try { FileStorage::contractPath($bad); $this->fail('must reject: ' . json_encode($bad)); } catch (\RuntimeException $e) { $this->assertSame('Invalid stored file name.', $e->getMessage()); }
        }
    }

    public function testExtractedTextNamesAreAlsoLocked(): void
    {
        $this->assertStringContainsString('/extracted_text/', FileStorage::textPath(str_repeat('0f', 16) . '.txt'));
        $this->expectException(\RuntimeException::class);
        FileStorage::textPath('../contracts/' . str_repeat('0f', 16) . '.pdf');
    }

    public function testDeleteIgnoresInvalidNames(): void
    {
        FileStorage::deleteContractFile('../../.env');
        FileStorage::deleteText('../../composer.json');
        $this->assertFileExists(ROOT_PATH . '/composer.json');
    }

    public function testAnotherUserCannotResolveTheContractSoCannotDownloadItsFile(): void
    {
        $owner = $this->user('manager'); $intruder = $this->user('manager'); $viewer = $this->user('viewer');
        $cid = $this->contract($owner, ['stored_filename' => str_repeat('c3', 16) . '.pdf', 'original_filename' => 'secret.pdf']);
        $this->assertNotNull(ContractModel::find($cid, $owner));
        $this->assertNull(ContractModel::find($cid, $intruder), 'download endpoints call find() first and answer 404');
        $this->assertNull(ContractModel::find($cid, $viewer));
        $this->assertNotNull(ContractModel::find($cid, $this->user('admin')));
    }

    public function testAllVersionFilesAreCollectedForCleanup(): void
    {
        $m = $this->user('manager');
        $a = str_repeat('a1', 16) . '.txt'; $b = str_repeat('b2', 16) . '.txt'; $ta = str_repeat('c3', 16) . '.txt'; $tb = str_repeat('d4', 16) . '.txt';
        $cid = $this->contract($m, ['stored_filename' => $b, 'extracted_text_path' => $tb]);
        $pdo = \TermTrail\config\Database::pdo();
        $ins = $pdo->prepare('INSERT INTO contract_versions (contract_id,version_number,original_filename,stored_filename,extracted_text_path,uploaded_by) VALUES (?,?,?,?,?,?)');
        $ins->execute([$cid, 1, 'a.txt', $a, $ta, $m['id']]); $ins->execute([$cid, 2, 'b.txt', $b, $tb, $m['id']]);
        $files = VersionService::allFiles(ContractModel::find($cid, $m));
        sort($files['stored']); sort($files['text']);
        $this->assertSame([$a, $b], $files['stored']);
        $this->assertSame([$ta, $tb], $files['text']);
    }
}
