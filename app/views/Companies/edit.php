<?php
declare(strict_types=1);

//require APP_ROOT . '/app/views/layouts/header.php';

$isEdit = ($mode ?? 'create') === 'edit';
$companyId = $company['company_id'] ?? null;

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$action = $isEdit
    ? '/index.php?page=companies_update&company_id=' . urlencode((string)$companyId)
    : '/index.php?page=companies_store';

$errors = $errors ?? [];
$companyTypes = $companyTypes ?? [];
$linkPeople = $linkPeople ?? [];
$employees = $employees ?? [];
$townEmployees = $townEmployees ?? [];
?>

<div class="d-flex align-items-center mb-3">
    <h1 class="h4 me-auto"><?= $isEdit ? 'Edit Company' : 'Create Company' ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="/index.php?page=companies">Back to Companies</a>
</div>

<?php if ($isEdit && $companyId): ?>
    <div class="text-muted small mb-3">Company ID: <?= (int)$companyId ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= h($action) ?>" class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label">Company Name</label>
                <input class="form-control" name="name" value="<?= h($company['name'] ?? '') ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Company Category</label>
                <select class="form-select" name="type">
                    <?php foreach (['internal','customer','vendor','partner','other'] as $t): ?>
                        <option value="<?= h($t) ?>" <?= (($company['type'] ?? 'vendor') === $t) ? 'selected' : '' ?>>
                            <?= h(ucfirst($t)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label d-block">Active</label>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                        <?= ((int)($company['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Company Type (LLC/Corp/etc.)</label>
                <select class="form-select" name="company_type_id">
                    <option value="">(none)</option>
                    <?php foreach ($companyTypes as $ct): ?>
                        <option value="<?= (int)$ct['company_type_id'] ?>"
                            <?= ((string)($company['company_type_id'] ?? '') === (string)$ct['company_type_id']) ? 'selected' : '' ?>>
                            <?= h($ct['company_type']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">State of Incorporation</label>
                <input class="form-control" name="state_of_incorporation" value="<?= h($company['state_of_incorporation'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Vendor ID</label>
                <input class="form-control" name="vendor_id" value="<?= h($company['vendor_id'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Phone</label>
                <input class="form-control" name="phone" value="<?= h($company['phone'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input class="form-control" name="email" value="<?= h($company['email'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Tax ID</label>
                <input class="form-control" name="tax_id" value="<?= h($company['tax_id'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Contact Name</label>
                <input class="form-control" name="contact_name" value="<?= h($company['contact_name'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Verified By (Town staff)</label>
                <input class="form-control" name="verified_by" value="<?= h($company['verified_by'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">COI Expiration Date</label>
                <input type="date" name="coi_exp_date" class="form-control" value="<?= h($company['coi_exp_date'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">COI Carrier</label>
                <input name="coi_carrier" class="form-control" value="<?= h($company['coi_carrier'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">COI Verified By</label>
                <select name="coi_verified_by_person_id" class="form-select">
                    <option value="">(not verified)</option>
                    <?php foreach ($townEmployees as $p): ?>
                        <option value="<?= (int)$p['person_id'] ?>"
                            <?= ((string)($company['coi_verified_by_person_id'] ?? '') === (string)$p['person_id']) ? 'selected' : '' ?>>
                            <?= h($p['display_name']) ?><?= !empty($p['email']) ? ' — ' . h($p['email']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label">Address (single line)</label>
                <input class="form-control" name="address" value="<?= h($company['address'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Address Line 1</label>
                <input class="form-control" name="address_line1" value="<?= h($company['address_line1'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Address Line 2</label>
                <input class="form-control" name="address_line2" value="<?= h($company['address_line2'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">City</label>
                <input class="form-control" name="city" value="<?= h($company['city'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">State/Region</label>
                <input class="form-control" name="state_region" value="<?= h($company['state_region'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Postal Code</label>
                <input class="form-control" name="postal_code" value="<?= h($company['postal_code'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Country</label>
                <input class="form-control" name="country" value="<?= h($company['country'] ?? '') ?>">
            </div>

        </div>
    </div>

    <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary"><?= $isEdit ? 'Save' : 'Create' ?></button>
        <a class="btn btn-outline-secondary" href="/index.php?page=companies">Back</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="fw-semibold">People at this Company</div>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-primary"
                   href="/index.php?page=people_create&company_id=<?= (int)$companyId ?>">
                    Add New Person
                </a>

                <button type="button"
                        class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal"
                        data-bs-target="#linkPersonModal">
                    Link Existing Person
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (!$employees): ?>
                <div class="p-3 text-muted">No people linked to this company yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Office</th>
                                <th>Cell</th>
                                <th>Town?</th>
                                <th>Department</th>
                                <th>Active</th>
                                <th style="width: 220px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $e): ?>
                                <tr>
                                    <td><?= h($e['person_name'] ?? '') ?></td>
                                    <td><?= h($e['email'] ?? '') ?></td>
                                    <td><?= h($e['officephone'] ?? '') ?></td>
                                    <td><?= h($e['cellphone'] ?? '') ?></td>
                                    <td><?= ((int)($e['is_town_employee'] ?? 0) === 1) ? 'Yes' : 'No' ?></td>
                                    <td><?= h($e['department_name'] ?? '') ?></td>
                                    <td><?= ((int)($e['is_active'] ?? 1) === 1) ? 'Yes' : 'No' ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="/people_edit.php?id=<?= (int)$e['person_id'] ?>">
                                            Edit
                                        </a>

                                        <form method="post"
                                              action="/index.php?page=companies_unlink_person&company_id=<?= (int)$companyId ?>"
                                              class="d-inline"
                                              onsubmit="return confirm('Unlink this person from this company?');">
                                            <input type="hidden" name="person_id" value="<?= (int)$e['person_id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Unlink</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="linkPersonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="post"
                  action="/index.php?page=companies_link_person&company_id=<?= (int)$companyId ?>"
                  class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Link Existing Person</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <label class="form-label">Select Person</label>
                    <select name="person_id" class="form-select" required>
                        <option value="">Select…</option>
                        <?php foreach ($linkPeople as $p): ?>
                            <?php
                            $name = trim(($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? ''));
                            if ($name === ',' || $name === '') {
                                $name = 'Person #' . (int)$p['person_id'];
                            }
                            $label = $name . (!empty($p['email']) ? ' — ' . $p['email'] : '');
                            if (!empty($p['company_id'])) {
                                $label .= ' (currently linked)';
                            }
                            ?>
                            <option value="<?= (int)$p['person_id'] ?>"><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (!$linkPeople): ?>
                        <div class="text-muted small mt-2">No active people found.</div>
                    <?php endif; ?>

                    <div class="form-text mt-2">
                        Linking will assign the person’s company_id to this company.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Link</button>
                </div>
            </form>
        </div>
    </div>

        <?php // Company Comments Block: $comments is provided by controller ?>
    <div class="card shadow-sm mt-4">
      <div class="card-header fw-semibold">Company Comments (Internal)</div>
      <div class="card-body">
        <?php if (function_exists('can_edit_company') ? can_edit_company($companyId) : true): ?>
          <form method="post" action="/company_comment_create.php" class="mb-3">
            <input type="hidden" name="company_id" value="<?= (int)$companyId ?>">
            <label class="form-label">Add Comment</label>
            <textarea class="form-control" name="comment_text" rows="3" required></textarea>
            <button class="btn btn-sm btn-primary mt-2">Add</button>
          </form>
        <?php endif; ?>
        <?php if (empty($comments)): ?>
          <div class="text-muted">No comments yet.</div>
        <?php else: ?>
          <div class="list-group">
            <?php foreach ($comments as $cmt): ?>
              <div class="list-group-item">
                <div class="d-flex justify-content-between">
                  <div class="small text-muted">
                    <?= h($cmt['created_at']) ?> • <?= h($cmt['author_name']) ?>
                  </div>
                  <?php if ((function_exists('is_system_admin') && is_system_admin()) || (function_exists('current_person_id') && (int)$cmt['person_id'] === current_person_id())): ?>
                    <form method="post" action="/company_comment_delete.php"
                          onsubmit="return confirm('Delete this comment?');">
                      <input type="hidden" name="company_comment_id" value="<?= (int)$cmt['company_comment_id'] ?>">
                      <input type="hidden" name="company_id" value="<?= (int)$companyId ?>">
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  <?php endif; ?>
                </div>
                <div class="mt-2"><?= nl2br(h($cmt['comment_text'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
<?php endif; ?>

<?php require APP_ROOT . '/app/views/layouts/footer.php'; ?>