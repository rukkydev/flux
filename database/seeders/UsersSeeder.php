<?php

// ─────────────────────────────────────────
//  Seeder: UsersSeeder
//  Creates demo user accounts
// ─────────────────────────────────────────

function run(): void
{
    $users = [
        [
            'name'              => 'Demo User',
            'email'             => 'demo@fluxphp.dev',
            'password'          => password_make('password'),
            'email_verified_at' => date('Y-m-d H:i:s'),
        ],
    ];

    foreach ($users as $user) {
        if (!db_exists('users', ['email' => $user['email']])) {
            db_insert('users', $user);
            echo "  Seeded user: {$user['email']}\n";
        } else {
            echo "  Skipped (exists): {$user['email']}\n";
        }
    }
}

run();
