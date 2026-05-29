<?php

declare(strict_types=1);

// ─────────────────────────────────────────
//  Module: user — Validation Rules
// ─────────────────────────────────────────

function user_validate_create(array $data): array
{
    return validate($data, [
        'name'                  => 'required|min:2|max:255',
        'email'                 => 'required|email|max:255',
        'password'              => 'required|min:8|confirmed',
        'password_confirmation' => 'required',
    ]);
}

function user_validate_update(array $data): array
{
    return validate($data, [
        'name'  => 'sometimes|min:2|max:255',
        'email' => 'sometimes|email|max:255',
    ]);
}

function user_validate_password(array $data): array
{
    return validate($data, [
        'current_password'      => 'required',
        'password'              => 'required|min:8|confirmed',
        'password_confirmation' => 'required',
    ]);
}
