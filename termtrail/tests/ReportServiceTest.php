<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use TermTrail\config\Database;
use TermTrail\services\{CsvExporter, ReportService};

final class ReportServiceTest extends DbTestCase
{
    public function testReportsOnlyContainTheUsersOwnRecords(): void
    {
        $a = $this->user('manager'); $b = $this->user('manager'); $admin = $this->user('admin');
        $this->contract($a, ['title' => 'A one']); $this->contract($a, ['title' => 'A two']); $this->contract($b, ['title' => 'B secret']);
        $titles = fn(array $u) => array_column(ReportService::build('contracts', [], $u)['rows'], 'title');
        $this->assertSame(['A one', 'A two'], $titles($a));
        $this->assertSame(['B secret'], $titles($b));
        $this->assertCount(3, $titles($admin));
    }

    public function testActivityReportIsRestricted(): void
    {
        $viewer = $this->user('viewer'); $manager = $this->user('manager'); $admin = $this->user('admin');
        $this->assertFalse(ReportService::allowed('activity', $viewer));
        $this->assertTrue(ReportService::allowed('activity', $manager));
        $this->assertFalse(ReportService::allowed('nonsense', $admin));
        $this->expectException(\InvalidArgumentException::class);
        ReportService::build('activity', [], $viewer);
    }

    public function testManagersSeeOnlyTheirOwnActivityAdminsSeeAll(): void
    {
        $m = $this->user('manager'); $other = $this->user('manager'); $admin = $this->user('admin');
        $ins = Database::pdo()->prepare('INSERT INTO activity_logs (user_id,action) VALUES (?,?)');
        $ins->execute([$m['id'], 'contract.create']); $ins->execute([$other['id'], 'contract.delete']);
        $this->assertSame(['contract.create'], array_column(ReportService::build('activity', [], $m)['rows'], 'action'));
        $this->assertCount(2, ReportService::build('activity', [], $admin)['rows']);
        $this->assertArrayNotHasKey('ip', ReportService::build('activity', [], $m)['rows'][0], 'IP addresses are admin-only');
    }

    public function testFiltersApplyAndAreReportedBack(): void
    {
        $m = $this->user('manager');
        $this->contract($m, ['title' => 'Active one', 'status' => 'active']); $this->contract($m, ['title' => 'Draft one', 'status' => 'draft']);
        $r = ReportService::build('contracts', ['status' => 'draft', 'risk' => 'bogus'], $m);
        $this->assertSame(['Draft one'], array_column($r['rows'], 'title'));
        $this->assertSame(['Status' => 'draft'], $r['filters'], 'invalid filter values are ignored, not echoed');
    }

    public function testRowLimitMarksTheReportTruncated(): void
    {
        $m = $this->user('manager'); for ($i = 0; $i < 4; $i++) { $this->contract($m); }
        $r = ReportService::build('contracts', [], $m, 3);
        $this->assertCount(3, $r['rows']);
        $this->assertTrue($r['truncated']);
    }

    public function testHostileValuesInRecordsAreEscapedInTheCsv(): void
    {
        $m = $this->user('manager');
        $cid = $this->contract($m, ['title' => '=HYPERLINK("http://evil.test","click")']);
        Database::pdo()->prepare('INSERT INTO obligations (contract_id,title) VALUES (?,?)')->execute([$cid, '+cmd|\' /C calc\'!A0']);
        $csv = CsvExporter::build(ReportService::build('obligations', [], $m));
        $this->assertStringContainsString("'+cmd|", $csv);
        $csv = CsvExporter::build(ReportService::build('contracts', [], $m));
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function testPaymentsReportScopesAndFiltersCurrency(): void
    {
        $a = $this->user('manager'); $b = $this->user('manager');
        $ca = $this->contract($a); $cb = $this->contract($b);
        $ins = Database::pdo()->prepare('INSERT INTO payments (contract_id,amount,currency,due_date) VALUES (?,?,?,?)');
        $ins->execute([$ca, 100, 'INR', '2030-01-01']); $ins->execute([$ca, 5, 'USD', '2030-01-02']); $ins->execute([$cb, 999, 'INR', '2030-01-03']);
        $this->assertCount(2, ReportService::build('payments', [], $a)['rows']);
        $this->assertSame(['5.00'], array_column(ReportService::build('payments', ['currency' => 'USD'], $a)['rows'], 'amount'));
    }
}
