<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use TermTrail\config\Database;
use TermTrail\services\{Lifecycle, ReminderService, VectorMath};

final class LifecycleTest extends DbTestCase
{
    public function testNextDateClampsMonthEnds(): void
    {
        $this->assertSame('2026-02-28', Lifecycle::nextDate('2026-01-31', 'monthly'));
        $this->assertSame('2028-02-29', Lifecycle::nextDate('2028-01-31', 'monthly'), 'leap year');
        $this->assertSame('2027-02-28', Lifecycle::nextDate('2026-11-30', 'quarterly'));
        $this->assertSame('2025-02-28', Lifecycle::nextDate('2024-02-29', 'yearly'));
        $this->assertSame('2026-10-28', Lifecycle::nextDate('2026-10-21', 'weekly'));
        $this->assertSame('2027-01-31', Lifecycle::nextDate('2026-12-31', 'monthly'));
        $this->assertNull(Lifecycle::nextDate('2026-10-21', 'none'));
    }

    public function testReminderWindowPicksTheSmallestCoveringWindow(): void
    {
        $this->assertSame(7, ReminderService::window(5, [30, 14, 7, 1]));
        $this->assertSame(1, ReminderService::window(0, [30, 14, 7, 1]));
        $this->assertSame(30, ReminderService::window(30, [1, 7, 14, 30]));
        $this->assertNull(ReminderService::window(31, [30, 14, 7, 1]));
    }

    public function testSyncMarksOverdueAndDueStatuses(): void
    {
        $m = $this->user('manager'); $cid = $this->contract($m); $pdo = Database::pdo();
        $day = fn(int $n) => date('Y-m-d', strtotime("$n days"));
        $o = $pdo->prepare('INSERT INTO obligations (contract_id,title,due_date,status) VALUES (?,?,?,?)');
        $o->execute([$cid, 'late', $day(-2), 'open']); $o->execute([$cid, 'future', $day(10), 'open']); $o->execute([$cid, 'done', $day(-9), 'completed']);
        $p = $pdo->prepare('INSERT INTO payments (contract_id,amount,due_date,status) VALUES (?,?,?,?)');
        $p->execute([$cid, 10, $day(-1), 'planned']); $p->execute([$cid, 20, $day(3), 'planned']); $p->execute([$cid, 30, $day(40), 'planned']); $p->execute([$cid, 40, $day(-5), 'paid']);
        Lifecycle::sync();
        $this->assertSame(['late' => 'overdue', 'future' => 'open', 'done' => 'completed'], $pdo->query('SELECT title,status FROM obligations')->fetchAll(\PDO::FETCH_KEY_PAIR));
        $this->assertSame(['10.00' => 'overdue', '20.00' => 'due', '30.00' => 'planned', '40.00' => 'paid'], $pdo->query('SELECT amount,status FROM payments')->fetchAll(\PDO::FETCH_KEY_PAIR));
    }

    public function testAssigneeMustBeOwnerReviewerOrAdmin(): void
    {
        $owner = $this->user('manager'); $rev = $this->user('viewer'); $stranger = $this->user('manager'); $admin = $this->user('admin');
        $cid = $this->contract($owner, ['reviewer_id' => $rev['id']]);
        $this->assertTrue(Lifecycle::validAssignee($cid, $owner['id']));
        $this->assertTrue(Lifecycle::validAssignee($cid, $rev['id']));
        $this->assertTrue(Lifecycle::validAssignee($cid, $admin['id']));
        $this->assertFalse(Lifecycle::validAssignee($cid, $stranger['id']));
        Database::pdo()->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$owner['id']]);
        $this->assertFalse(Lifecycle::validAssignee($cid, $owner['id']), 'inactive users cannot be assigned');
    }

    public function testVectorMath(): void
    {
        $this->assertEqualsWithDelta(1.0, VectorMath::cosine([1, 2, 3], [2, 4, 6]), 1e-9);
        $this->assertEqualsWithDelta(0.0, VectorMath::cosine([1, 0], [0, 1]), 1e-9);
        $this->assertEqualsWithDelta(-1.0, VectorMath::cosine([1, 1], [-1, -1]), 1e-9);
        $this->assertNull(VectorMath::cosine([1, 2], [1, 2, 3]));
        $this->assertNull(VectorMath::cosine([0, 0], [1, 1]));
        $this->assertNotNull(VectorMath::validate(array_fill(0, 8, 0.1)));
        $this->assertNull(VectorMath::validate([1, 2, 3]));
        $this->assertNull(VectorMath::validate(array_merge(array_fill(0, 8, 1), ['x'])));
        $this->assertNull(VectorMath::validate(array_merge(array_fill(0, 8, 1), [NAN])));
        $this->assertNull(VectorMath::validate(array_merge(array_fill(0, 8, 1), [INF])));
        $this->assertNull(VectorMath::validate('not an array'));
    }
}
