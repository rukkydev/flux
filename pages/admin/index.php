<?php

// ─────────────────────────────────────────
//  Page: /admin
//  Admin Dashboard
// ─────────────────────────────────────────

title('Admin Dashboard');
layout('dashboard');

$totalUsers    = db_count('users');
$activeUsers   = db_count('users', ['deleted_at' => null]);
$recentLogins  = db_table('activity_logs')
    ->where('event', 'auth.login')
    ->order('created_at', 'DESC')
    ->limit(5)
    ->get();

$recentUsers = db_table('users')
    ->whereNull('deleted_at')
    ->order('created_at', 'DESC')
    ->limit(5)
    ->get();
?>

<?php section('sidebar') ?>
<?php component('admin.sidebar') ?>
<?php end_section() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Dashboard</h1>
    <span class="text-muted small">Welcome back, <?= e(session_get('auth.name')) ?></span>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?= number_format($totalUsers) ?></div>
            <div class="stat-meta">All registered accounts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Active Users</div>
            <div class="stat-value"><?= number_format($activeUsers) ?></div>
            <div class="stat-meta">Non-deleted accounts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Queue Jobs</div>
            <div class="stat-value"><?= array_sum(queue_stats()) ?></div>
            <div class="stat-meta">Pending background jobs</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Cache Driver</div>
            <div class="stat-value" style="font-size:1.2rem"><?= ucfirst(cache_driver()) ?></div>
            <div class="stat-meta"><?= config('app.env') ?> environment</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Users -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                Recent Users
                <a href="<?= url('/admin/users') ?>" class="btn btn-sm btn-outline-primary ms-auto">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Name</th><th>Role</th><th>Joined</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentUsers as $u): ?>
                        <tr>
                            <td>
                                <a href="<?= url('/admin/users/' . $u['id']) ?>"><?= e($u['name']) ?></a>
                                <div class="small text-muted"><?= e($u['email']) ?></div>
                            </td>
                            <td><span class="badge bg-primary"><?= e($u['role']) ?></span></td>
                            <td class="text-muted small"><?= date('M d', strtotime($u['created_at'])) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Recent Activity</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Event</th><th>IP</th><th>When</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentLogins as $log): ?>
                        <tr>
                            <td><?= e($log['event']) ?></td>
                            <td class="text-muted small"><?= e($log['ip_address'] ?? '—') ?></td>
                            <td class="text-muted small"><?= date('M d H:i', strtotime($log['created_at'])) ?></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (empty($recentLogins)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No activity yet.</td></tr>
                    <?php endif ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
