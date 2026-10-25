<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use TermTrail\models\ContractModel;

final class AuthorizationTest extends DbTestCase
{
    public function testOwnerManagerCanReadAndWrite(): void
    {
        $m = $this->user('manager'); $cid = $this->contract($m);
        $c = ContractModel::find($cid, $m);
        $this->assertNotNull($c);
        $this->assertTrue(ContractModel::canWrite($c, $m));
        $this->assertTrue(ContractModel::canNote($c, $m));
    }

    public function testAnotherManagerCannotSeeTheContractAtAll(): void
    {
        $owner = $this->user('manager'); $other = $this->user('manager'); $cid = $this->contract($owner);
        $this->assertNull(ContractModel::find($cid, $other), 'no cross-user access (appears as not found)');
        $this->assertSame(0, ContractModel::search([], $other)['total']);
        $this->assertSame(1, ContractModel::search([], $owner)['total']);
    }

    public function testAdminSeesEverythingAndCanWrite(): void
    {
        $owner = $this->user('manager'); $admin = $this->user('admin'); $cid = $this->contract($owner);
        $c = ContractModel::find($cid, $admin);
        $this->assertNotNull($c);
        $this->assertTrue(ContractModel::canWrite($c, $admin));
    }

    public function testAssignedViewerIsReadOnly(): void
    {
        $owner = $this->user('manager'); $viewer = $this->user('viewer');
        $cid = $this->contract($owner, ['reviewer_id' => $viewer['id']]);
        $c = ContractModel::find($cid, $viewer);
        $this->assertNotNull($c, 'an assigned reviewer can read');
        $this->assertFalse(ContractModel::canWrite($c, $viewer));
        $this->assertFalse(ContractModel::canNote($c, $viewer), 'viewers cannot add notes');
    }

    public function testAssignedManagerReviewerCanNoteButNotWrite(): void
    {
        $owner = $this->user('manager'); $rev = $this->user('manager');
        $c = ContractModel::find($this->contract($owner, ['reviewer_id' => $rev['id']]), $rev);
        $this->assertFalse(ContractModel::canWrite($c, $rev));
        $this->assertTrue(ContractModel::canNote($c, $rev));
    }

    public function testUnassignedViewerSeesNothing(): void
    {
        $owner = $this->user('manager'); $viewer = $this->user('viewer'); $this->contract($owner);
        $this->assertSame(0, ContractModel::search([], $viewer)['total']);
    }

    public function testPublicRowHidesStorageDetails(): void
    {
        $m = $this->user('manager');
        $cid = $this->contract($m, ['stored_filename' => str_repeat('a', 32) . '.txt', 'extracted_text_path' => str_repeat('b', 32) . '.txt']);
        $row = ContractModel::publicRow(ContractModel::find($cid, $m));
        $this->assertArrayNotHasKey('stored_filename', $row);
        $this->assertArrayNotHasKey('extracted_text_path', $row);
        $this->assertTrue($row['has_file']);
    }

    public function testSortWhitelistBlocksInjection(): void
    {
        $m = $this->user('manager'); $this->contract($m);
        $r = ContractModel::search(['sort' => 'title; DROP TABLE contracts', 'dir' => 'sideways', 'q' => "x' OR '1'='1"], $m);
        $this->assertSame(0, $r['total'], 'hostile search text is just a string');
        $this->assertSame(1, ContractModel::search([], $m)['total'], 'table still exists');
    }
}
