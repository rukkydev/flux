<?php

$userId = (int) route_param('id');
$user   = user_find_or_fail($userId);

title('Edit — ' . $user['name']);
layout('dashboard');

if (request_is('POST') && request('_action') === 'update') {
    $data   = request_only(['name', 'email', 'role']);
    $errors = validation_run($data, [
        'name'  => 'required|min:2|max:255',
        'email' => 'required|email',
        'role'  => 'required|in:user,admin,moderator',
    ]);
    if (!empty($errors)) { $_SESSION['_errors'] = $errors; back(); }
    user_update($userId, $data);
    flash('success', 'User updated.');
    redirect(url('/admin/users/' . $userId));
}

if (request_is('POST') && request('_action') === 'delete') {
    user_delete($userId);
    flash('success', 'User deleted.');
    redirect(url('/admin/users'));
}

$errors   = validation_errors();
$activity = db_table('activity_logs')
    ->where('user_id', $userId)
    ->order('created_at', 'DESC')
    ->limit(10)
    ->get();
?>

<?php section('sidebar') ?>
<?php component('admin.sidebar') ?>
<?php end_section() ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= url('/admin/users') ?>" class="btn btn-outline-secondary btn-sm">← Back</a>
    <h1 class="mb-0"><?= e($user['name']) ?></h1>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Edit User</div>
            <div class="card-body">
                <?php partial('alerts', ['errors' => $errors]) ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="update">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control <?= error_class('name') ?>"
                               value="<?= e(old('name', $user['name'])) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control <?= error_class('email') ?>"
                               value="<?= e(old('email', $user['email'])) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <?php foreach (['user', 'admin', 'moderator'] as $role): ?>
                                <option value="<?= $role ?>" <?= $user['role'] === $role ? 'selected' : '' ?>>
                                    <?= ucfirst($role) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <button class="btn btn-dark">Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Account Info</div>
            <div class="card-body">
                <div class="small text-muted mb-1">Joined</div>
                <div class="mb-3"><?= date('M d, Y', strtotime($user['created_at'])) ?></div>
                <div class="small text-muted mb-1">Last Login</div>
                <div class="mb-3"><?= $user['last_login_at'] ? date('M d, Y H:i', strtotime($user['last_login_at'])) : 'Never' ?></div>
                <div class="small text-muted mb-1">Email Verified</div>
                <div><?= $user['email_verified_at'] ? '✓ Verified' : '✗ Not verified' ?></div>
            </div>
        </div>

        <div class="card border-danger">
            <div class="card-header text-danger">Danger Zone</div>
            <div class="card-body">
                <p class="small text-muted">This will soft-delete the user account.</p>
                <form method="POST" onsubmit="return confirm('Delete this user?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="delete">
                    <button class="btn btn-sm btn-outline-danger">Delete User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($activity)): ?>
<div class="card mt-3">
    <div class="card-header">Recent Activity</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Event</th><th>IP</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($activity as $log): ?>
                <tr>
                    <td><?= e($log['event']) ?></td>
                    <td class="text-muted small"><?= e($log['ip_address'] ?? '—') ?></td>
                    <td class="text-muted small"><?= date('M d H:i', strtotime($log['created_at'])) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif ?>
