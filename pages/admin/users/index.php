<?php

title('Users');
layout('dashboard');

$page    = (int) (request('page') ?? 1);
$search  = request('search', '');
$results = $search ? user_search($search, $page) : user_all($page);
?>

<?php section('sidebar') ?>
<?php component('admin.sidebar') ?>
<?php end_section() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Users</h1>
    <a href="<?= url('/admin/users/create') ?>" class="btn btn-dark">+ New User</a>
</div>

<!-- Search -->
<form method="GET" class="mb-3">
    <div class="input-group" style="max-width:360px">
        <input type="text" name="search" class="form-control"
               placeholder="Search by name..." value="<?= e($search) ?>">
        <button class="btn btn-outline-secondary">Search</button>
        <?php if ($search): ?>
            <a href="<?= url('/admin/users') ?>" class="btn btn-outline-danger">Clear</a>
        <?php endif ?>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Last Login</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results['data'] as $user): ?>
                <tr>
                    <td class="fw-medium"><?= e($user['name']) ?></td>
                    <td class="text-muted"><?= e($user['email']) ?></td>
                    <td><span class="badge bg-primary"><?= e($user['role']) ?></span></td>
                    <td class="text-muted small"><?= date('M d Y', strtotime($user['created_at'])) ?></td>
                    <td class="text-muted small"><?= $user['last_login_at'] ? date('M d Y', strtotime($user['last_login_at'])) : '—' ?></td>
                    <td class="text-end">
                        <a href="<?= url('/admin/users/' . $user['id']) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    </td>
                </tr>
            <?php endforeach ?>
            <?php if (empty($results['data'])): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>
            <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3"><?php component('pagination', ['paginator' => $results]) ?></div>
