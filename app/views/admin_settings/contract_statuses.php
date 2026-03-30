<?php
// app/views/admin_settings/contract_statuses.php
?>
<div class="container mt-4">
    <h2 class="h4 mb-3">Contract Statuses</h2>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">Status updated successfully.</div>
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
            <?php foreach ($statuses as $status): ?>
                <tr>
                    <form method="post" action="/index.php?page=admin_statuses_update">
                        <input type="hidden" name="contract_status_id" value="<?= (int)$status['contract_status_id'] ?>">
                        <td><?= (int)$status['contract_status_id'] ?></td>
                        <td><input type="text" name="contract_status_name" value="<?= h($status['contract_status_name']) ?>" class="form-control form-control-sm" required></td>
                        <td><input type="text" name="contract_status_desc" value="<?= h($status['contract_status_desc']) ?>" class="form-control form-control-sm"></td>
                        <td>
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                    </form>
                    <form method="post" action="/index.php?page=admin_statuses_delete" style="display:inline">
                        <input type="hidden" name="contract_status_id" value="<?= (int)$status['contract_status_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger ms-1" onclick="return confirm('Delete this status?')">Delete</button>
                    </form>
                        </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <form method="post" action="/index.php?page=admin_statuses_create">
                    <td></td>
                    <td><input type="text" name="contract_status_name" class="form-control form-control-sm" placeholder="New status name" required></td>
                    <td><input type="text" name="contract_status_desc" class="form-control form-control-sm" placeholder="Description"></td>
                    <td><button type="submit" class="btn btn-sm btn-success">Add</button></td>
                </form>
            </tr>
        </tbody>
    </table>
    <a href="/index.php?page=admin_settings" class="btn btn-outline-secondary mt-3">Back to System Settings</a>
</div>
