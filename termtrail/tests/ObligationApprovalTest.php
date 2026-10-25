<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use TermTrail\config\Database;
use TermTrail\services\{SuggestionException, SuggestionService};

final class ObligationApprovalTest extends DbTestCase
{
    private function suggestion(int $cid, array $o = []): int
    {
        $o += ['kind' => 'obligation', 'title' => 'Submit monthly report', 'description' => 'Each month', 'responsible_party' => 'Provider', 'due_date' => '2026-12-05', 'amount' => null,
            'currency' => null, 'recurrence' => 'monthly', 'source_quote' => 'shall submit a monthly report', 'quote_verified' => 1];
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO obligation_suggestions (contract_id,kind,title,description,responsible_party,due_date,amount,currency,recurrence,source_quote,quote_verified) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$cid, $o['kind'], $o['title'], $o['description'], $o['responsible_party'], $o['due_date'], $o['amount'], $o['currency'], $o['recurrence'], $o['source_quote'], $o['quote_verified']]);
        return (int)$pdo->lastInsertId();
    }

    private function rows(string $table): int { return (int)Database::pdo()->query("SELECT COUNT(*) FROM $table")->fetchColumn(); }

    public function testApprovalCreatesObligationWithApprovalMetadata(): void
    {
        $m = $this->user('manager'); $cid = $this->contract($m); $sid = $this->suggestion($cid);
        $this->assertSame(0, $this->rows('obligations'), 'a suggestion is not a commitment');
        $r = SuggestionService::approve($sid, $m, []);
        $this->assertSame('obligation', $r['kind']);
        $o = Database::pdo()->query('SELECT * FROM obligations')->fetch();
        $this->assertSame('Submit monthly report', $o['title']);
        $this->assertSame('monthly', $o['recurrence']);
        $this->assertSame($sid, (int)$o['suggestion_id']);
        $this->assertSame($m['id'], (int)$o['approved_by']);
        $this->assertNotNull($o['approved_at']);
        $this->assertSame('shall submit a monthly report', $o['source_quote']);
        $this->assertSame('approved', Database::pdo()->query("SELECT status FROM obligation_suggestions WHERE id=$sid")->fetchColumn());
    }

    public function testReviewerCanEditBeforeApproving(): void
    {
        $m = $this->user('manager'); $cid = $this->contract($m); $sid = $this->suggestion($cid);
        SuggestionService::approve($sid, $m, ['title' => 'Edited title', 'due_date' => '2027-01-10', 'priority' => 'high', 'recurrence' => 'none']);
        $o = Database::pdo()->query('SELECT * FROM obligations')->fetch();
        $this->assertSame(['Edited title', '2027-01-10', 'high', null], [$o['title'], $o['due_date'], $o['priority'], $o['recurrence']]);
    }

    public function testCannotApproveTwice(): void
    {
        $m = $this->user('manager'); $sid = $this->suggestion($this->contract($m));
        SuggestionService::approve($sid, $m, []);
        try { SuggestionService::approve($sid, $m, []); $this->fail('expected conflict'); }
        catch (SuggestionException $e) { $this->assertSame(409, $e->status); }
        $this->assertSame(1, $this->rows('obligations'), 'no duplicate created');
    }

    public function testPaymentNeedsADueDateAndNothingIsCreatedOtherwise(): void
    {
        $m = $this->user('manager'); $sid = $this->suggestion($this->contract($m), ['kind' => 'payment', 'title' => 'Fee', 'amount' => '50000.00', 'currency' => 'INR', 'due_date' => null, 'recurrence' => null]);
        try { SuggestionService::approve($sid, $m, []); $this->fail('expected validation error'); }
        catch (SuggestionException $e) { $this->assertSame(422, $e->status); $this->assertArrayHasKey('due_date', $e->errors); }
        $this->assertSame(0, $this->rows('payments'));
        $this->assertSame('pending', Database::pdo()->query("SELECT status FROM obligation_suggestions WHERE id=$sid")->fetchColumn(), 'stays pending after a failed approval');
        SuggestionService::approve($sid, $m, ['due_date' => '2026-11-01']);
        $p = Database::pdo()->query('SELECT * FROM payments')->fetch();
        $this->assertSame(['50000.00', 'INR', '2026-11-01', $sid], [$p['amount'], $p['currency'], $p['due_date'], (int)$p['suggestion_id']]);
    }

    public function testRejectRecordsDecisionAndReason(): void
    {
        $m = $this->user('manager'); $sid = $this->suggestion($this->contract($m));
        SuggestionService::reject($sid, $m, 'Handled elsewhere');
        $row = Database::pdo()->query("SELECT status, reject_reason, decided_by FROM obligation_suggestions WHERE id=$sid")->fetch();
        $this->assertSame(['rejected', 'Handled elsewhere', $m['id']], [$row['status'], $row['reject_reason'], (int)$row['decided_by']]);
        $this->assertSame(0, $this->rows('obligations'));
        $this->expectException(SuggestionException::class);
        SuggestionService::approve($sid, $m, []);
    }

    public function testOnlyTheContractOwnerOrAdminMayDecide(): void
    {
        $owner = $this->user('manager'); $other = $this->user('manager'); $viewer = $this->user('viewer'); $admin = $this->user('admin');
        $cid = $this->contract($owner, ['reviewer_id' => $viewer['id']]); $sid = $this->suggestion($cid);
        foreach ([[$other, 404], [$viewer, 403]] as [$u, $code]) {
            try { SuggestionService::approve($sid, $u, []); $this->fail('expected refusal'); } catch (SuggestionException $e) { $this->assertSame($code, $e->status, $u['role']); }
        }
        $this->assertSame(0, $this->rows('obligations'));
        $this->assertSame('obligation', SuggestionService::approve($sid, $admin, [])['kind']);
    }

    public function testUnknownSuggestionIsNotFound(): void
    {
        $this->expectException(SuggestionException::class);
        SuggestionService::approve(99999, $this->user('admin'), []);
    }
}
