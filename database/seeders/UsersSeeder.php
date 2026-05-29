<?php

// ─────────────────────────────────────────
//  Seeder: UsersSeeder
//  Creates default admin + demo users
// ─────────────────────────────────────────

function run(): void
{
    $users = [
        [
            'name'              => 'Admin User',
            'email'             => 'admin@fluxphp.dev',
            'password'          => password_make('password'),
            'role'              => 'admin',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ],
        [
            'name'              => 'Demo User',
            'email'             => 'demo@fluxphp.dev',
            'password'          => password_make('password'),
            'role'              => 'user',
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
