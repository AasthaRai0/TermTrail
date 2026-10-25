<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use TermTrail\controllers\ContractController;

final class ContractValidationTest extends DbTestCase
{
    private function fields(array $in, ?array $user = null): array { return ContractController::fields($in, $user ?? $this->user('manager')); }

    public function testValidContract(): void
    {
        [$d, $e] = $this->fields(['title' => ' MSA ', 'status' => 'active', 'currency' => 'usd', 'contract_value' => '1,250.50', 'start_date' => '2026-01-01', 'expiry_date' => '2026-12-31', 'notice_period_days' => '30']);
        $this->assertSame([], $e);
        $this->assertSame('MSA', $d['title']);
        $this->assertSame('USD', $d['currency']);
        $this->assertSame('1250.50', $d['contract_value']);
        $this->assertSame(30, $d['notice_period_days']);
    }

    public function testTitleIsRequired(): void
    {
        $this->assertArrayHasKey('title', $this->fields(['title' => '   '])[1]);
    }

    public function testDatesMustBeRealCalendarDates(): void
    {
        foreach (['2026-02-30', '2026-13-01', '31/12/2026', 'tomorrow', '2026-1-1'] as $bad) {
            $this->assertArrayHasKey('expiry_date', $this->fields(['title' => 'x', 'expiry_date' => $bad])[1], $bad);
        }
        $this->assertSame([], $this->fields(['title' => 'x', 'expiry_date' => '2028-02-29'])[1], 'leap day is valid');
    }

    public function testExpiryCannotPrecedeStart(): void
    {
        $e = $this->fields(['title' => 'x', 'start_date' => '2026-06-01', 'expiry_date' => '2026-01-01'])[1];
        $this->assertArrayHasKey('expiry_date', $e);
    }

    public function testValueAndNoticeAndCurrencyAndStatus(): void
    {
        $e = $this->fields(['title' => 'x', 'contract_value' => 'abc', 'notice_period_days' => '-3', 'currency' => 'XXX', 'status' => 'bogus'])[1];
        foreach (['contract_value', 'notice_period_days', 'currency', 'status'] as $k) { $this->assertArrayHasKey($k, $e, $k); }
        $this->assertArrayHasKey('contract_value', $this->fields(['title' => 'x', 'contract_value' => '1.234'])[1]);
        $this->assertArrayHasKey('notice_period_days', $this->fields(['title' => 'x', 'notice_period_days' => '99999'])[1]);
    }

    public function testLongTextIsClipped(): void
    {
        [$d] = $this->fields(['title' => str_repeat('t', 400), 'description' => str_repeat('d', 9000), 'tags' => str_repeat('g', 400)]);
        $this->assertSame(255, mb_strlen($d['title']));
        $this->assertSame(5000, mb_strlen($d['description']));
        $this->assertSame(255, mb_strlen($d['tags']));
    }

    public function testCounterpartyMustBelongToTheUser(): void
    {
        $owner = $this->user('manager'); $other = $this->user('manager');
        \TermTrail\config\Database::pdo()->prepare('INSERT INTO counterparties (owner_id,name) VALUES (?,?)')->execute([$owner['id'], 'Acme']);
        $cp = (int)\TermTrail\config\Database::pdo()->lastInsertId();
        $this->assertSame([], $this->fields(['title' => 'x', 'counterparty_id' => $cp], $owner)[1]);
        $this->assertArrayHasKey('counterparty_id', $this->fields(['title' => 'x', 'counterparty_id' => $cp], $other)[1]);
        $this->assertArrayHasKey('counterparty_id', $this->fields(['title' => 'x', 'counterparty_id' => 99999], $owner)[1]);
    }

    public function testReviewerMustBeAnActiveUser(): void
    {
        $m = $this->user('manager'); $r = $this->user('viewer');
        $this->assertSame([], $this->fields(['title' => 'x', 'reviewer_id' => $r['id']], $m)[1]);
        \TermTrail\config\Database::pdo()->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$r['id']]);
        $this->assertArrayHasKey('reviewer_id', $this->fields(['title' => 'x', 'reviewer_id' => $r['id']], $m)[1]);
        $this->assertArrayHasKey('reviewer_id', $this->fields(['title' => 'x', 'reviewer_id' => 424242], $m)[1]);
    }
}
