<?php

// ─────────────────────────────────────────
//  Seeder: AdminsSeeder
//  Creates default admin account
//  WARNING: Change password after first login!
// ─────────────────────────────────────────

function run(): void
{
    $admins = [
        [
            'name'      => 'Super Admin',
            'email'     => 'admin@fluxphp.dev',
            'password'  => password_make('Admin@1234!'),
            'is_active' => 1,
        ],
    ];

    foreach ($admins as $admin) {
        if (!db_exists('admins', ['email' => $admin['email']])) {
            db_insert('admins', $admin);
            echo "  Seeded admin: {$admin['email']} (password: Admin@1234!)\n";
            echo "  *** CHANGE THIS PASSWORD IMMEDIATELY IN PRODUCTION ***\n";
        } else {
            echo "  Skipped (exists): {$admin['email']}\n";
        }
    }
}

run();
