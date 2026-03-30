<?php
// expects: $pdo, $company_id (or $company['company_id']), and auth.php loaded

$cid = (int)($company_id ?? ($company['company_id'] ?? 0));
$comments = [];

$cs = $pdo->prepare("
  SELECT
    cc.company_comment_id,
    cc.comment_text,
    cc.created_at,
    p.person_id,
    COALESCE(NULLIF(p.full_name,''), CONCAT_WS(' ', p.first_name, p.last_name)) AS author_name
  FROM company_comments cc
  JOIN people p ON p.person_id = cc.person_id
  WHERE cc.company_id = ?
  ORDER BY cc.created_at DESC, cc.company_comment_id DESC
");
$cs->execute([$cid]);
$comments = $cs->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card shadow-sm mt-4">
  <div class="card-header fw-semibold">Company Comments (Internal)</div>
  <div class="card-body">

    <?php if (can_edit_company()): ?>
      <form method="post" action="/company_comment_create.php" class="mb-3">
        <input type="hidden" name="company_id" value="<?= (int)$cid ?>">
        <label class="form-label">Add Comment</label>
        <textarea class="form-control" name="comment_text" rows="3" required></textarea>
        <button class="btn btn-sm btn-primary mt-2">Add</button>
      </form>
    <?php endif; ?>

    <?php if (!$comments): ?>
      <div class="text-muted">No comments yet.</div>
    <?php else: ?>
      <div class="list-group">
        <?php foreach ($comments as $cmt): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between">
              <div class="small text-muted">
                <?= h($cmt['created_at']) ?> • <?= h($cmt['author_name']) ?>
              </div>

              <?php if (is_system_admin() || (int)$cmt['person_id'] === current_person_id()): ?>
                <form method="post" action="/company_comment_delete.php"
                      onsubmit="return confirm('Delete this comment?');">
                  <input type="hidden" name="company_comment_id" value="<?= (int)$cmt['company_comment_id'] ?>">
                  <input type="hidden" name="company_id" value="<?= (int)$cid ?>">
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
