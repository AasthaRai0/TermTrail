<?php
declare(strict_types=1);
namespace TermTrail\services;

use TermTrail\config\Database;
use TermTrail\helpers\Validator;
use TermTrail\models\ContractModel;

/** Builds report datasets from live, access-controlled database records. */
final class ReportService
{
    public const TYPES = [
        'contracts' => 'Contract summary', 'risks' => 'Risk findings', 'expirations' => 'Upcoming expirations', 'obligations' => 'Obligations',
        'payments' => 'Payments', 'counterparties' => 'Counterparties', 'activity' => 'Activity',
    ];

    public static function allowed(string $type, array $u): bool
    {
        if (!isset(self::TYPES[$type])) { return false; }
        return $type !== 'activity' || in_array($u['role'], ['admin', 'manager'], true);
    }

    private static function col(string $key, string $label): array { return ['key' => $key, 'label' => $label]; }

    private static function run(string $sql, array $p): array
    {
        $st = Database::pdo()->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    }

    private static function dateFilters(array $q, string $col, array &$where, array &$p, string $fromKey = 'date_from', string $toKey = 'date_to'): array
    {
        $applied = [];
        foreach ([$fromKey => '>=', $toKey => '<='] as $k => $op) {
            if (Validator::date((string)($q[$k] ?? ''))) { $where[] = "$col $op ?"; $p[] = $q[$k]; $applied[$k === $fromKey ? 'From' : 'To'] = $q[$k]; }
        }
        return $applied;
    }

    /** @return array{type:string,title:string,columns:array,rows:array,truncated:bool,filters:array} */
    public static function build(string $type, array $q, array $u, int $limit = 5000): array
    {
        if (!self::allowed($type, $u)) { throw new \InvalidArgumentException('You cannot run this report.'); }
        $r = match ($type) {
            'contracts' => self::contracts($q, $u, $limit), 'risks' => self::risks($q, $u, $limit), 'expirations' => self::expirations($q, $u, $limit),
            'obligations' => self::obligations($q, $u, $limit), 'payments' => self::payments($q, $u, $limit), 'counterparties' => self::counterparties($u, $limit),
            'activity' => self::activity($q, $u, $limit),
        };
        $truncated = count($r['rows']) > $limit;
        if ($truncated) { $r['rows'] = array_slice($r['rows'], 0, $limit); }
        return $r + ['type' => $type, 'title' => self::TYPES[$type] . ' report', 'truncated' => $truncated];
    }

    private static function contracts(array $q, array $u, int $limit): array
    {
        [$w, $p] = ContractModel::scope($u);
        $where = [$w, 'c.is_archived=0']; $f = [];
        if (in_array($q['status'] ?? '', ContractModel::STATUSES, true)) { $where[] = 'c.status=?'; $p[] = $q['status']; $f['Status'] = $q['status']; }
        if (in_array($q['risk'] ?? '', ['low', 'medium', 'high'], true)) { $where[] = 'c.risk_level=?'; $p[] = $q['risk']; $f['Risk'] = $q['risk']; }
        if ((int)($q['counterparty_id'] ?? 0) > 0) { $where[] = 'c.counterparty_id=?'; $p[] = (int)$q['counterparty_id']; $f['Counterparty ID'] = (int)$q['counterparty_id']; }
        $f += self::dateFilters($q, 'c.expiry_date', $where, $p);
        $rows = self::run('SELECT c.id,c.title,cp.name AS counterparty,c.contract_type,c.status,c.risk_level,c.risk_score,c.effective_date,c.start_date,c.expiry_date,c.renewal_date,c.notice_period_days,
            c.contract_value,c.currency,rv.name AS reviewer,c.tags FROM contracts c LEFT JOIN counterparties cp ON cp.id=c.counterparty_id LEFT JOIN users rv ON rv.id=c.reviewer_id
            WHERE ' . implode(' AND ', $where) . ' ORDER BY c.title LIMIT ' . ($limit + 1), $p);
        return ['columns' => [self::col('id', 'ID'), self::col('title', 'Title'), self::col('counterparty', 'Counterparty'), self::col('contract_type', 'Type'), self::col('status', 'Status'),
            self::col('risk_level', 'Risk level'), self::col('risk_score', 'Risk score'), self::col('effective_date', 'Effective'), self::col('start_date', 'Start'), self::col('expiry_date', 'Expiry'),
            self::col('renewal_date', 'Renewal'), self::col('notice_period_days', 'Notice (days)'), self::col('contract_value', 'Value'), self::col('currency', 'Currency'), self::col('reviewer', 'Reviewer'), self::col('tags', 'Tags')],
            'rows' => $rows, 'filters' => $f];
    }

    private static function risks(array $q, array $u, int $limit): array
    {
        [$w, $p] = ContractModel::scope($u);
        $where = [$w, 'c.is_archived=0']; $f = [];
        if (in_array($q['severity'] ?? '', ['low', 'medium', 'high'], true)) { $where[] = 'f.severity=?'; $p[] = $q['severity']; $f['Severity'] = $q['severity']; }
        if (in_array($q['review_status'] ?? '', ['pending', 'reviewed', 'dismissed'], true)) { $where[] = 'f.review_status=?'; $p[] = $q['review_status']; $f['Review status'] = $q['review_status']; }
        if ((int)($q['contract_id'] ?? 0) > 0) { $where[] = 'f.contract_id=?'; $p[] = (int)$q['contract_id']; $f['Contract ID'] = (int)$q['contract_id']; }
        $rows = self::run("SELECT c.title AS contract, f.severity, f.title AS finding, f.description, f.reason, f.source_quote AS evidence,
            CASE WHEN f.source_quote IS NULL THEN 'No quotation' WHEN f.quote_verified=1 THEN 'Verified' ELSE 'Not verified' END AS evidence_status,
            IF(f.is_inferred=1,'Inferred','Detected') AS origin, cl.clause_type, f.review_status, f.reviewer_comment, f.suggested_action
            FROM risk_findings f JOIN contracts c ON c.id=f.contract_id LEFT JOIN clauses cl ON cl.id=f.clause_id WHERE " . implode(' AND ', $where) .
            " ORDER BY FIELD(f.severity,'high','medium','low'), c.title, f.id LIMIT " . ($limit + 1), $p);
        return ['columns' => [self::col('contract', 'Contract'), self::col('severity', 'Severity'), self::col('finding', 'Finding'), self::col('description', 'Description'), self::col('reason', 'Reason'),
            self::col('evidence', 'Evidence quotation'), self::col('evidence_status', 'Evidence check'), self::col('origin', 'Origin'), self::col('clause_type', 'Clause'),
            self::col('review_status', 'Review status'), self::col('reviewer_comment', 'Reviewer comment'), self::col('suggested_action', 'Suggested follow-up')], 'rows' => $rows, 'filters' => $f];
    }

    private static function expirations(array $q, array $u, int $limit): array
    {
        [$w, $p] = ContractModel::scope($u);
        $days = min(730, max(7, (int)($q['days'] ?? 90)));
        $rows = self::run("SELECT c.title AS contract, cp.name AS counterparty, c.status, c.expiry_date, DATEDIFF(c.expiry_date, CURDATE()) AS days_left, c.renewal_date, c.notice_period_days,
            DATE_SUB(COALESCE(c.renewal_date,c.expiry_date), INTERVAL c.notice_period_days DAY) AS notice_deadline, c.renewal_decision, c.contract_value, c.currency
            FROM contracts c LEFT JOIN counterparties cp ON cp.id=c.counterparty_id WHERE $w AND c.is_archived=0 AND c.status IN ('active','under_review') AND c.expiry_date IS NOT NULL
            AND c.expiry_date BETWEEN (CURDATE() - INTERVAL 30 DAY) AND (CURDATE() + INTERVAL $days DAY) ORDER BY c.expiry_date LIMIT " . ($limit + 1), $p);
        return ['columns' => [self::col('contract', 'Contract'), self::col('counterparty', 'Counterparty'), self::col('status', 'Status'), self::col('expiry_date', 'Expiry'), self::col('days_left', 'Days left'),
            self::col('renewal_date', 'Renewal date'), self::col('notice_period_days', 'Notice (days)'), self::col('notice_deadline', 'Notice deadline'), self::col('renewal_decision', 'Renewal decision'),
            self::col('contract_value', 'Value'), self::col('currency', 'Currency')], 'rows' => $rows, 'filters' => ['Window' => "next $days days (and the last 30)"]];
    }

    private static function obligations(array $q, array $u, int $limit): array
    {
        Lifecycle::sync();
        [$w, $p] = ContractModel::scope($u);
        $where = [$w, 'c.is_archived=0']; $f = [];
        $st = (string)($q['status'] ?? '');
        if (in_array($st, ['open', 'completed', 'overdue', 'cancelled'], true)) { $where[] = 'o.status=?'; $p[] = $st; $f['Status'] = $st; }
        elseif ($st === 'active') { $where[] = "o.status IN ('open','overdue')"; $f['Status'] = 'open & overdue'; }
        if (in_array($q['priority'] ?? '', ['low', 'medium', 'high'], true)) { $where[] = 'o.priority=?'; $p[] = $q['priority']; $f['Priority'] = $q['priority']; }
        if ((int)($q['contract_id'] ?? 0) > 0) { $where[] = 'o.contract_id=?'; $p[] = (int)$q['contract_id']; $f['Contract ID'] = (int)$q['contract_id']; }
        $f += self::dateFilters($q, 'o.due_date', $where, $p);
        $rows = self::run('SELECT c.title AS contract, o.title, o.description, o.responsible_party, ow.name AS owner, o.due_date, o.priority, o.status, o.recurrence, o.completion_notes,
            IF(o.approved_by IS NULL,"Manual","Approved AI suggestion") AS origin FROM obligations o JOIN contracts c ON c.id=o.contract_id LEFT JOIN users ow ON ow.id=o.owner_id
            WHERE ' . implode(' AND ', $where) . ' ORDER BY (o.due_date IS NULL), o.due_date, o.id LIMIT ' . ($limit + 1), $p);
        return ['columns' => [self::col('contract', 'Contract'), self::col('title', 'Obligation'), self::col('description', 'Description'), self::col('responsible_party', 'Responsible party'), self::col('owner', 'Internal owner'),
            self::col('due_date', 'Due date'), self::col('priority', 'Priority'), self::col('status', 'Status'), self::col('recurrence', 'Recurrence'), self::col('completion_notes', 'Completion notes'), self::col('origin', 'Origin')],
            'rows' => $rows, 'filters' => $f];
    }

    private static function payments(array $q, array $u, int $limit): array
    {
        Lifecycle::sync();
        [$w, $p] = ContractModel::scope($u);
        $where = [$w, 'c.is_archived=0']; $f = [];
        $st = (string)($q['status'] ?? '');
        if (in_array($st, ['planned', 'due', 'paid', 'overdue', 'cancelled'], true)) { $where[] = 'y.status=?'; $p[] = $st; $f['Status'] = $st; }
        elseif ($st === 'upcoming') { $where[] = "y.status IN ('planned','due') AND y.due_date >= CURDATE()"; $f['Status'] = 'upcoming'; }
        if ((int)($q['contract_id'] ?? 0) > 0) { $where[] = 'y.contract_id=?'; $p[] = (int)$q['contract_id']; $f['Contract ID'] = (int)$q['contract_id']; }
        if (in_array($q['currency'] ?? '', AiJson::CURRENCIES, true)) { $where[] = 'y.currency=?'; $p[] = $q['currency']; $f['Currency'] = $q['currency']; }
        $f += self::dateFilters($q, 'y.due_date', $where, $p);
        $rows = self::run('SELECT c.title AS contract, y.amount, y.currency, y.category, y.due_date, y.status, y.paid_date, y.reference_number, y.notes
            FROM payments y JOIN contracts c ON c.id=y.contract_id WHERE ' . implode(' AND ', $where) . ' ORDER BY y.due_date, y.id LIMIT ' . ($limit + 1), $p);
        return ['columns' => [self::col('contract', 'Contract'), self::col('amount', 'Amount'), self::col('currency', 'Currency'), self::col('category', 'Category'), self::col('due_date', 'Due date'),
            self::col('status', 'Status'), self::col('paid_date', 'Paid on'), self::col('reference_number', 'Reference'), self::col('notes', 'Notes')], 'rows' => $rows, 'filters' => $f];
    }

    private static function counterparties(array $u, int $limit): array
    {
        [$cw, $cp] = ContractModel::scope($u, 'c2');
        if ($u['role'] === 'admin') { $vis = '1=1'; $vp = []; } else { $vis = '(cp.owner_id = ? OR EXISTS (SELECT 1 FROM contracts rc WHERE rc.counterparty_id=cp.id AND rc.reviewer_id = ?))'; $vp = [(int)$u['id'], (int)$u['id']]; }
        $rows = self::run("SELECT cp.name, cp.contact_person, cp.email, cp.phone, IF(cp.is_archived=1,'Archived','Active') AS state,
            (SELECT COUNT(*) FROM contracts c2 WHERE c2.counterparty_id=cp.id AND c2.is_archived=0 AND $cw) AS contracts,
            (SELECT COUNT(*) FROM contracts c2 WHERE c2.counterparty_id=cp.id AND c2.is_archived=0 AND c2.status='active' AND $cw) AS active_contracts,
            (SELECT MIN(c2.expiry_date) FROM contracts c2 WHERE c2.counterparty_id=cp.id AND c2.is_archived=0 AND c2.status='active' AND c2.expiry_date >= CURDATE() AND $cw) AS next_expiry
            FROM counterparties cp WHERE $vis ORDER BY cp.name LIMIT " . ($limit + 1), array_merge($cp, $cp, $cp, $vp));
        return ['columns' => [self::col('name', 'Name'), self::col('contact_person', 'Contact'), self::col('email', 'Email'), self::col('phone', 'Phone'), self::col('state', 'State'),
            self::col('contracts', 'Contracts'), self::col('active_contracts', 'Active contracts'), self::col('next_expiry', 'Next expiry')], 'rows' => $rows, 'filters' => []];
    }

    private static function activity(array $q, array $u, int $limit): array
    {
        $where = ['1=1']; $p = []; $f = [];
        if ($u['role'] !== 'admin') { $where[] = 'al.user_id=?'; $p[] = (int)$u['id']; $f['Scope'] = 'your own actions'; }
        elseif ((int)($q['user_id'] ?? 0) > 0) { $where[] = 'al.user_id=?'; $p[] = (int)$q['user_id']; $f['User ID'] = (int)$q['user_id']; }
        if (preg_match('/^[a-z_]+(\.[a-z_]+)?$/', (string)($q['action'] ?? ''))) { $where[] = 'al.action LIKE ?'; $p[] = $q['action'] . '%'; $f['Action'] = $q['action']; }
        $f += self::dateFilters($q, 'DATE(al.created_at)', $where, $p);
        $rows = self::run('SELECT al.created_at AS time, us.name AS user, al.action, al.entity_type, al.entity_id, al.metadata AS details' . ($u['role'] === 'admin' ? ', al.ip_address AS ip' : '') .
            ' FROM activity_logs al LEFT JOIN users us ON us.id=al.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY al.id DESC LIMIT ' . ($limit + 1), $p);
        $cols = [self::col('time', 'Time (server)'), self::col('user', 'User'), self::col('action', 'Action'), self::col('entity_type', 'Record type'), self::col('entity_id', 'Record ID'), self::col('details', 'Details')];
        if ($u['role'] === 'admin') { $cols[] = self::col('ip', 'IP address'); }
        return ['columns' => $cols, 'rows' => $rows, 'filters' => $f];
    }
}
