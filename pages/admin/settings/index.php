<?php

title('Settings');
layout('dashboard');

if (request_is('POST')) {
    $data = request_only(['app_name', 'app_env', 'app_debug', 'log_level']);

    // Update .env values
    foreach ([
        'APP_NAME'  => $data['app_name']  ?? '',
        'APP_ENV'   => $data['app_env']   ?? 'production',
        'APP_DEBUG' => isset($data['app_debug']) ? 'true' : 'false',
        'LOG_LEVEL' => $data['log_level'] ?? 'error',
    ] as $key => $value) {
        env_update($key, $value);
    }

    flash('success', 'Settings saved.');
    redirect(url('/admin/settings'));
}
?>

<?php section('sidebar') ?>
<?php component('admin.sidebar') ?>
<?php end_section() ?>

<h1 class="mb-4">Settings</h1>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Application</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">App Name</label>
                        <input type="text" name="app_name" class="form-control"
                               value="<?= e(config('app.name')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Environment</label>
                        <select name="app_env" class="form-select">
                            <?php foreach (['local', 'staging', 'production'] as $env): ?>
                                <option value="<?= $env ?>" <?= config('app.env') === $env ? 'selected' : '' ?>>
                                    <?= ucfirst($env) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Log Level</label>
                        <select name="log_level" class="form-select">
                            <?php foreach (['debug', 'info', 'warning', 'error', 'critical'] as $level): ?>
                                <option value="<?= $level ?>" <?= env('LOG_LEVEL') === $level ? 'selected' : '' ?>>
                                    <?= ucfirst($level) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" name="app_debug" class="form-check-input" id="debug"
                               <?= is_debug() ? 'checked' : '' ?>>
                        <label class="form-check-label" for="debug">Enable debug mode</label>
                    </div>

                    <button class="btn btn-dark">Save Settings</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">System Info</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">PHP Version</span>
                    <strong><?= PHP_VERSION ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">FluxPHP</span>
                    <strong><?= config('app.version', '1.0.0') ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">DB Driver</span>
                    <strong><?= config('database.default') ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Cache Driver</span>
                    <strong><?= cache_driver() ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Queue Jobs</span>
                    <strong><?= array_sum(queue_stats()) ?></strong>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Cache</div>
            <div class="card-body">
                <p class="small text-muted">Clear all cached routes, config, and app data.</p>
                <a href="<?= url('/admin/settings/cache-clear') ?>"
                   class="btn btn-sm btn-outline-warning"
                   onclick="return confirm('Clear all cache?')">
                    Clear Cache
                </a>
            </div>
        </div>
    </div>
</div>
