<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: auth — Validation Rules
// ─────────────────────────────────────────

function auth_validate_login(array $data): array
{
    return validate($data, [
        'email'    => 'required|email',
        'password' => 'required',
    ]);
}

function auth_validate_register(array $data): array
{
    return validate($data, [
        'name'                  => 'required|min:2|max:255',
        'email'                 => 'required|email|max:255',
        'password'              => 'required|min:8|confirmed',
        'password_confirmation' => 'required',
    ]);
}

function auth_validate_reset(array $data): array
{
    return validate($data, [
        'password'              => 'required|min:8|confirmed',
        'password_confirmation' => 'required',
    ]);
}
