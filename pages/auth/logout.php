<?php

// ─────────────────────────────────────────
//  Page: /logout
//  Middleware: auth
// ─────────────────────────────────────────

middleware('auth');

auth_logout();
flash('success', 'You have been logged out.');
redirect('/login');
