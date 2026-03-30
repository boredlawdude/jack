<?php
// app/views/admin_settings/payment_terms.php
?>
<div class="container mt-4">
    <h2 class="h4 mb-3">Payment Terms</h2>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">Payment term updated successfully.</div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= h($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <table class="table table-bordered align-middle bg-white">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th style="width:120px">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($terms as $term): ?>
                <tr>
                    <form method="post" action="/index.php?page=admin_payment_terms_update">
                        <input type="hidden" name="payment_terms_id" value="<?= (int)$term['payment_terms_id'] ?>">
                        <td><?= (int)$term['payment_terms_id'] ?></td>
                        <td><input type="text" name="name" value="<?= h($term['name']) ?>" class="form-control form-control-sm" required></td>
                        <td><input type="text" name="description" value="<?= h($term['description']) ?>" class="form-control form-control-sm"></td>
                        <td>
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                    </form>
                    <form method="post" action="/index.php?page=admin_payment_terms_delete" style="display:inline">
                        <input type="hidden" name="payment_terms_id" value="<?= (int)$term['payment_terms_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger ms-1" onclick="return confirm('Delete this payment term?')">Delete</button>
                    </form>
                        </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <form method="post" action="/index.php?page=admin_payment_terms_create">
                    <td></td>
                    <td><input type="text" name="name" class="form-control form-control-sm" placeholder="New payment term name" required></td>
                    <td><input type="text" name="description" class="form-control form-control-sm" placeholder="Description"></td>
                    <td><button type="submit" class="btn btn-sm btn-success">Add</button></td>
                </form>
            </tr>
        </tbody>
    </table>
    <a href="/index.php?page=admin_settings" class="btn btn-outline-secondary mt-3">Back to System Settings</a>
</div>
