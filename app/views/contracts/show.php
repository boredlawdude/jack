<?php
$contractTitle  = trim((string)($contract['name'] ?? 'Contract'));
$contractNumber = trim((string)($contract['contract_number'] ?? ''));
$status         = trim((string)($contract['status_name'] ?? ''));
?>

<div class="container py-4">

  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <div class="text-muted small mb-1">Contract Detail</div>
      <h1 class="h3 mb-1"><?= h($contractTitle) ?></h1>
      <div class="text-muted">
        <?php if ($contractNumber !== ''): ?>
          <span class="me-3"><strong>No.</strong> <?= h($contractNumber) ?></span>
        <?php endif; ?>
        <?php if ($status !== ''): ?>
          <?php if (!function_exists('status_badge')) {
              function status_badge(string $status): string {
                  return match (strtolower($status)) {
                      'draft' => 'secondary',
                      'negotiate' => 'info',
                      'procurement review' => 'info',
                      'legal review' => 'warning',
                      'dept head review' => 'primary',
                      'manager review' => 'primary',
                      'town council' => 'info',
                      'out for signature' => 'warning',
                      'executed' => 'success',
                      default => 'light',
                  };
              }
          } ?>
          <span class="badge text-bg-<?= status_badge($status) ?>"><?= h($status) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="/index.php?page=contracts" class="btn btn-outline-secondary btn-sm">Back</a>
      <a href="/index.php?page=contracts_edit&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-primary btn-sm">Change Contract Info</a>
      <a href="/index.php?page=contracts_generate_html&contract_id=<?= (int)$contract['contract_id'] ?>" target="_blank" class="btn btn-outline-success btn-sm">Generate HTML</a>
      <a href="/index.php?page=contracts_generate_word&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-outline-info btn-sm">Generate Word Doc</a>
    </div>
  </div>

  <div class="row g-4">

    <div class="col-lg-8">

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h2 class="h6 mb-0">Summary</h2>
        </div>
        <div class="card-body">
          <div class="row g-3">
            
            <div class="col-md-6">
              <div class="small text-muted">Department</div>
              <div><?= h($contract['department_name'] ?? '') ?: '—' ?></div>
              <?php if (!empty($contract['department_code'])): ?>
                <div class="text-muted small">Code: <?= h($contract['department_code']) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Counterparty Company</div>
              <div><?= h($contract['counterparty_company_name'] ?? '') ?: '—' ?></div>
            </div>
            

            <div class="col-md-6">
              <div class="small text-muted">Contract Type</div>
              <div><?= h($contract['contract_type_name'] ?? '') ?: '—' ?></div>
            </div>
             <div class="col-md-6">
            <div class="small text-muted">Counterparty Primary Contact</div>
            <div><?= h($contract['counterparty_primary_contact_name'] ?? '') ?: '—'  ?></div>
            <?php if (!empty($contract['counterparty_primary_contact_email'])): ?>
              ( <a href="mailto:<?= h($contract['counterparty_primary_contact_email']) ?>">
                <?= h($contract['counterparty_primary_contact_email']) ?>
              </a> )
            <?php endif; ?>
            </div>

            <div class="col-md-6">
              <div class="small text-muted">Payment Terms</div>
              <div><?= h($contract['payment_terms_name'] ?? '') ?: '—' ?></div>
            </div>

           
           
        

            <div class="col-md-6">
              <div class="small text-muted">Start Date</div>
              <div><?= h($contract['start_date'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-muted">End Date</div>
              <div><?= h($contract['end_date'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-muted">Auto Renew</div>
              <div><?= !empty($contract['auto_renew']) ? 'Yes' : 'No' ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-muted">Renewal Term (Months)</div>
              <div><?= h($contract['renewal_term_months'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-muted">Created At</div>
              <div><?= h($contract['created_at'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-muted">Updated At</div>
              <div><?= h($contract['updated_at'] ?? '') ?: '—' ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h2 class="h6 mb-0">Financial / Terms</h2>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="small text-muted">Total Contract Value</div>
              <div><?= h($contract['total_contract_value'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-4">
            </div>

            <div class="col-md-4">
              <div class="small text-muted">Governing Law</div>
              <div><?= h($contract['governing_law'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-12">
              <div class="small text-muted">Payment Terms</div>
              <div><?= h($contract['payment_terms_name'] ?? '') ?: '—' ?></div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <div class="col-lg-4">

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h2 class="h6 mb-0">Quick Info</h2>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <div class="small text-muted">Town Primary Contact</div>
            <div><?= h($contract['owner_primary_contact_name'] ?? '') ?: '—'  ?></div>
            <?php if (!empty($contract['owner_primary_contact_email'])): ?>
                ( <a href="mailto:<?= h($contract['owner_primary_contact_email']) ?>">
                    <?= h($contract['owner_primary_contact_email']) ?>
                </a> )
            <?php endif; ?>
          </div>
          <div class="mb-3">
            <div class="small text-muted">Department Code</div>
            <div><?= h($contract['department_code'] ?? '') ?: '—' ?></div>
          </div>
          <div class="mb-3">
            <div class="small text-muted">Documents Path</div>
            <div><?= h($contract['documents_path'] ?? '') ?: '—' ?></div>
          </div>
          <div class="mb-0">
            <div class="small text-muted">Contract Body HTML</div>
            <div><?= !empty($contract['contract_body_html']) ? 'Present' : '—' ?></div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h2 class="h6 mb-0">Description</h2>
        </div>
        <div class="card-body">
          <?php $desc = trim((string)($contract['description'] ?? '')); ?>
          <?php if ($desc !== ''): ?>
            <div style="white-space: pre-wrap;"><?= h($desc) ?></div>
          <?php else: ?>
            <div class="text-muted">No description entered.</div>
          <?php endif; ?>
        </div>
      </div>

    </div>

  </div>

  <div class="card shadow-sm mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <h2 class="h6 mb-0">Documents</h2>
      <div class="d-flex gap-2">
            <a href="/index.php?page=contract_documents_merge_pdf&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-outline-dark btn-sm">Merge as PDF</a>
            <a href="/index.php?page=contract_document_compare&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-outline-info btn-sm">Compare Documents</a>
            <a href="/index.php?page=contract_document_create&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-outline-secondary btn-sm">Upload Document</a>
            <a href="/index.php?page=contracts_generate_word&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-outline-primary btn-sm">Generate Doc</a>
            <a href="/index.php?page=contracts_generate_html&contract_id=<?= (int)$contract['contract_id'] ?>" target="_blank" class="btn btn-outline-success btn-sm">Generate HTML</a>
          </div>
        </div>
        <div class="card-body p-0">
          <?php if (!empty($documents)): ?>
            <form method="post" action="/index.php?page=contract_documents_save_order">
              <input type="hidden" name="contract_id" value="<?= (int)$contract['contract_id'] ?>">
            <div class="table-responsive">
              <table class="table table-hover mb-0 align-middle">
                <thead>
                  <tr>
                    <th style="width:60px">#</th>
                    <th>Draft</th>
                    <th>Type</th>
                    <th>PDF Stamp</th>
                    <th>Description</th>
                    <th>File</th>
                    <th>Created</th>
                    <th>Created By</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($documents as $doc): ?>
                    <tr>
                      <td>
                        <input type="number" name="order[<?= (int)$doc['contract_document_id'] ?>]" value="<?= (int)($doc['sort_order'] ?? 0) ?>" class="form-control form-control-sm" style="width:55px" min="0">
                      </td>
                      <td><?= !empty($doc['file_name']) ? h($doc['file_name']) : '—' ?></td>
                      <td><?= !empty($doc['doc_type']) ? h($doc['doc_type']) : '—' ?></td>
                      <td>
                        <input type="text" name="exhibit_label[<?= (int)$doc['contract_document_id'] ?>]" value="<?= h($doc['exhibit_label'] ?? '') ?>" class="form-control form-control-sm" style="width:110px" maxlength="50" placeholder="no stamp">
                      </td>
                      <td><?= !empty($doc['description']) ? h($doc['description']) : '—' ?></td>
                      <td>
                        <?php
                          $webPath = '';
                          if (!empty($doc['file_path'])) {
                            $webPath = $doc['file_path'];
                            // Remove any absolute path prefix, keep only web path
                            if (strpos($webPath, '/storage/') === false && ($pos = strpos($webPath, 'storage/')) !== false) {
                              $webPath = '/' . substr($webPath, $pos);
                            } elseif (strpos($webPath, '/storage/') !== 0) {
                              $webPath = '/' . ltrim($webPath, '/');
                            }
                          }
                        ?>
                        <?php if ($webPath): ?>
                          <a href="<?= h($webPath) ?>" target="_blank">Open</a>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td><?= !empty($doc['created_at']) ? date('m/d/y H:i', strtotime($doc['created_at'])) : '—' ?></td>
                      <td><?= !empty($doc['created_by_name']) ? h($doc['created_by_name']) : '—' ?></td>
                      <td class="text-end">
                        <?php if (!empty($doc['contract_document_id']) && (int)$doc['contract_document_id'] > 0): ?>
                          <a href="/index.php?page=contract_document_email&id=<?= (int)$doc['contract_document_id'] ?>" class="btn btn-outline-primary btn-sm">Email Doc</a>
                          <button type="button" class="btn btn-outline-danger btn-sm ms-1" onclick="if(confirm('Delete this document?')){let f=document.createElement('form');f.method='post';f.action='/index.php?page=contract_document_delete';let i=document.createElement('input');i.type='hidden';i.name='document_id';i.value='<?= (int)$doc['contract_document_id'] ?>';f.appendChild(i);document.body.appendChild(f);f.submit();}">Delete</button>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
              <div class="p-2 text-end">
                <button type="submit" class="btn btn-sm btn-outline-primary">Save Order &amp; Labels</button>
              </div>
            </form>
          <?php else: ?>
            <div class="p-3 text-muted">No drafts added.</div>
          <?php endif; ?>
        </div>
      </div>

  <!-- Contract History -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0">Contract History</h2>
          <span class="badge bg-secondary"><?= count($history ?? []) ?></span>
        </div>
        <div class="card-body border-bottom py-2">
          <form method="post" action="/index.php?page=contract_history_add" class="d-flex gap-2 align-items-end">
            <input type="hidden" name="contract_id" value="<?= (int)$contract['contract_id'] ?>">
            <div class="flex-grow-1">
              <input type="text" class="form-control form-control-sm" name="note" placeholder="Add a note or comment..." required>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">Add Note</button>
          </form>
        </div>
        <?php if (!empty($history)): ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Date</th>
                  <th>Event</th>
                  <th>Details</th>
                  <th>By</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($history as $entry): ?>
                  <tr>
                    <td class="text-nowrap"><?= date('m/d/y H:i', strtotime($entry['changed_at'])) ?></td>
                    <td>
                      <?php
                        $eventLabels = [
                          'contract_created' => '<span class="badge bg-success">Created</span>',
                          'contract_updated' => '<span class="badge bg-info text-dark">Updated</span>',
                          'status_change' => '<span class="badge bg-warning text-dark">Status Change</span>',
                          'document_generated' => '<span class="badge bg-primary">Doc Generated</span>',
                          'document_uploaded' => '<span class="badge bg-primary">Doc Uploaded</span>',
                          'document_deleted' => '<span class="badge bg-danger">Doc Deleted</span>',
                          'document_emailed' => '<span class="badge bg-info text-dark">Doc Emailed</span>',
                          'manual_note' => '<span class="badge bg-dark">Note</span>',
                        ];
                        echo $eventLabels[$entry['event_type']] ?? '<span class="badge bg-secondary">' . h($entry['event_type']) . '</span>';
                      ?>
                    </td>
                    <td>
                      <?php if ($entry['event_type'] === 'status_change'): ?>
                        <?= h($entry['old_status'] ?? '') ?> &rarr; <?= h($entry['new_status'] ?? '') ?>
                      <?php else: ?>
                        <?= h($entry['notes'] ?? '') ?>
                      <?php endif; ?>
                    </td>
                    <td><?= h($entry['changed_by_name'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="p-3 text-muted">No history recorded yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="mt-4 d-flex gap-2">
    <a href="/index.php?page=contracts_edit&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-primary">Change Contract Info</a>
    <a href="/index.php?page=contract_document_create&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-outline-secondary">Add Document</a>
  </div>

</div>