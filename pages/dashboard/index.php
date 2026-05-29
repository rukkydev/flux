<?php

// ─────────────────────────────────────────
//  Page: /dashboard
//  Middleware: auth
// ─────────────────────────────────────────

middleware('auth');
title('Dashboard');
layout('dashboard');

$user = auth_user();
