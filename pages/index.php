<?php

title('Welcome');
meta('description', 'FluxPHP — Scalable Procedural PHP Framework');
layout('app');

?>

<!-- Hero -->
<div class="flux-hero">
    <h1>The PHP Framework Built<br>for <span>Real Work</span></h1>
    <p class="lead">Scalable. Procedural. Enterprise-ready.</p>
    <div class="d-flex gap-2 justify-content-center mt-4">
        <a href="/login" class="btn btn-dark btn-lg">Get Started</a>
        <a href="https://github.com/fluxphp/fluxphp" class="btn btn-outline-secondary btn-lg">GitHub</a>
    </div>
</div>

<!-- Features -->
<div class="row g-3 mb-5">
    <div class="col-md-4">
        <div class="flux-feature">
            <span class="flux-feature-icon">⚡</span>
            <h5>File-Based Routing</h5>
            <p>Drop a file in <code>pages/</code> and it becomes a route instantly. Dynamic segments like <code>[id]</code> resolve automatically — no registration needed.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="flux-feature">
            <span class="flux-feature-icon">🧩</span>
            <h5>Fat Modules</h5>
            <p>Business logic lives in domain modules. Pages stay thin. Reuse functions across your entire application without dependency injection.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="flux-feature">
            <span class="flux-feature-icon">🏢</span>
            <h5>Enterprise Scale</h5>
            <p>Built for real-world applications. Clean procedural architecture with no hidden magic, no service containers, no over-engineering.</p>
        </div>
    </div>
</div>

<?php push('scripts') ?>
<script>console.log('FluxPHP <?= config("app.version", "1.0") ?> loaded.');</script>
<?php end_push() ?>
