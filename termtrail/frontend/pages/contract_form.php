<?php
use TermTrail\helpers\Security as S;
$editing = ($page === 'contract_edit');
$cid = (int)($_GET['id'] ?? 0);
$title = $editing ? 'Edit contract' : 'New contract'; $active = 'contracts'; $scripts = ['contract_form.js'];
$opt = require __DIR__ . '/../components/options.php';
require __DIR__ . '/../components/app_shell_start.php';
$f = fn(string $id, string $label, string $inner, string $col = 'col-md-6') =>
    print('<div class="' . $col . '"><label class="form-label" for="' . $id . '">' . $label . '</label>' . $inner . '<div class="invalid-feedback" data-error-for="' . $id . '"></div></div>');
?>
<div class="page-head"><h1 class="h3"><?= S::e($title) ?></h1></div>
<form id="contractForm" class="glass p-4" data-api="<?= $editing ? 'contracts/update' : 'contracts/create' ?>" <?= $editing ? '' : 'data-multipart="1"' ?> data-edit-id="<?= $editing ? $cid : 0 ?>" novalidate>
  <?php if ($editing): ?><input type="hidden" name="id" value="<?= $cid ?>"><?php endif; ?>
  <div class="row g-3">
    <?php
    $f('title', 'Title *', '<input class="form-control" id="title" name="title" maxlength="255" required>', 'col-12');
    $f('contract_type', 'Contract type', '<select class="form-select" id="contract_type" name="contract_type"><option value="">Select…</option>' . implode('', array_map(fn($t) => '<option>' . S::e($t) . '</option>', $opt['types'])) . '</select>');
    $f('counterparty_id', 'Counterparty', '<select class="form-select" id="counterparty_id" name="counterparty_id"><option value="">None</option></select><div class="form-text"><a href="' . APP_URL . '/index.php?page=counterparties">Manage counterparties</a></div>');
    $f('status', 'Status', '<select class="form-select" id="status" name="status">' . implode('', array_map(fn($v, $l) => '<option value="' . S::e($v) . '">' . S::e($l) . '</option>', array_keys($opt['statuses']), $opt['statuses'])) . '</select>');
    $f('reviewer_id', 'Assigned reviewer', '<select class="form-select" id="reviewer_id" name="reviewer_id"><option value="">Unassigned</option></select>');
    $f('effective_date', 'Effective date', '<input type="date" class="form-control" id="effective_date" name="effective_date">', 'col-md-3');
    $f('start_date', 'Start date', '<input type="date" class="form-control" id="start_date" name="start_date">', 'col-md-3');
    $f('expiry_date', 'Expiry date', '<input type="date" class="form-control" id="expiry_date" name="expiry_date">', 'col-md-3');
    $f('renewal_date', 'Renewal date', '<input type="date" class="form-control" id="renewal_date" name="renewal_date">', 'col-md-3');
    $f('notice_period_days', 'Notice period (days)', '<input type="number" min="0" max="3650" class="form-control" id="notice_period_days" name="notice_period_days">', 'col-md-3');
    $f('contract_value', 'Contract value', '<input class="form-control" id="contract_value" name="contract_value" inputmode="decimal" placeholder="e.g. 50000.00">', 'col-md-5');
    $f('currency', 'Currency', '<select class="form-select" id="currency" name="currency">' . implode('', array_map(fn($c) => '<option>' . S::e($c) . '</option>', $opt['currencies'])) . '</select>', 'col-md-4');
    $f('tags', 'Tags', '<input class="form-control" id="tags" name="tags" maxlength="255" placeholder="comma separated">', 'col-12');
    $f('description', 'Description', '<textarea class="form-control" id="description" name="description" rows="3" maxlength="5000"></textarea>', 'col-12');
    if (!$editing) {
        $f('file', 'Contract file (PDF, DOCX or TXT, max 10 MB)', '<input type="file" class="form-control" id="file" name="file" accept=".pdf,.docx,.txt"><div class="form-text">Optional. Text is extracted automatically; scanned PDFs are not supported (no OCR).</div>', 'col-12');
    }
    ?>
  </div>
  <div class="alert alert-danger d-none mt-3" role="alert" data-form-error></div>
  <div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit"><span class="btn-label"><?= $editing ? 'Save changes' : 'Create contract' ?></span></button>
    <a class="btn btn-outline-secondary" href="<?= APP_URL ?>/index.php?page=<?= $editing ? 'contract_view&id=' . $cid : 'contracts' ?>">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../components/app_shell_end.php'; ?>
