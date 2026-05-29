<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Validation Engine
//
//  validate($_POST, [
//      'email'    => 'required|email',
//      'age'      => 'required|integer|min:18',
//      'password' => 'required|min:8|confirmed',
//  ]);
//
//  Returns clean $data array on success.
//  Calls abort / response_validation_error on failure.
// ═══════════════════════════════════════════════════════

/**
 * Validate input data against a rule set.
 * On failure: redirects back with errors (web) or JSON 422 (API).
 * On success: returns the validated data array.
 */
function validate(array $data, array $rules): array
{
    $errors = validation_run($data, $rules);

    if (!empty($errors)) {
        // Store old input for repopulation
        $_SESSION['_old_input'] = $data;

        if (request_is_api()) {
            response_validation_error($errors);
        }

        $_SESSION['_errors'] = $errors;
        back();
    }

    // Return only validated keys
    return arr_only($data, array_keys($rules));
}

/**
 * Run validation rules and return an error bag.
 * Does NOT abort — returns errors for manual handling.
 */
function validation_run(array $data, array $rules): array
{
    $errors = [];

    foreach ($rules as $field => $ruleString) {
        $fieldRules = explode('|', $ruleString);
        $value      = $data[$field] ?? null;

        // 'sometimes' — skip if field not present
        if (in_array('sometimes', $fieldRules, true) && !isset($data[$field])) {
            continue;
        }

        foreach ($fieldRules as $rule) {
            if ($rule === 'sometimes') continue;

            $error = validation_apply_rule($field, $value, $rule, $data);

            if ($error !== null) {
                $errors[$field][] = $error;
                break; // stop after first error per field
            }
        }
    }

    return $errors;
}

/**
 * Apply a single rule to a value.
 * Returns error message string or null if passes.
 */
function validation_apply_rule(string $field, mixed $value, string $rule, array $data): ?string
{
    $label = ucfirst(str_replace('_', ' ', $field));

    // Rules with parameters: min:8, max:255, etc.
    $param = null;
    if (str_contains($rule, ':')) {
        [$rule, $param] = explode(':', $rule, 2);
    }

    return match ($rule) {
        'required'  => (is_null($value) || $value === '' || $value === [])
                        ? "{$label} is required."
                        : null,

        'nullable'  => null, // always passes

        'string'    => (!is_null($value) && !is_string($value))
                        ? "{$label} must be a string."
                        : null,

        'integer', 'int' => (!is_null($value) && !filter_var($value, FILTER_VALIDATE_INT))
                        ? "{$label} must be an integer."
                        : null,

        'numeric'   => (!is_null($value) && !is_numeric($value))
                        ? "{$label} must be a number."
                        : null,

        'boolean', 'bool' => (!is_null($value) && !in_array($value, [true, false, 0, 1, '0', '1'], true))
                        ? "{$label} must be true or false."
                        : null,

        'email'     => (!is_null($value) && !filter_var($value, FILTER_VALIDATE_EMAIL))
                        ? "{$label} must be a valid email address."
                        : null,

        'url'       => (!is_null($value) && !filter_var($value, FILTER_VALIDATE_URL))
                        ? "{$label} must be a valid URL."
                        : null,

        'min'       => validation_rule_min($label, $value, (int) $param),
        'max'       => validation_rule_max($label, $value, (int) $param),
        'between'   => validation_rule_between($label, $value, $param),

        'confirmed' => ($value !== ($data[$field . '_confirmation'] ?? null))
                        ? "{$label} confirmation does not match."
                        : null,

        'same'      => ($value !== ($data[$param] ?? null))
                        ? "{$label} must match {$param}."
                        : null,

        'different' => ($value === ($data[$param] ?? null))
                        ? "{$label} must be different from {$param}."
                        : null,

        'in'        => (!is_null($value) && !in_array($value, explode(',', $param ?? ''), true))
                        ? "{$label} must be one of: {$param}."
                        : null,

        'not_in'    => (!is_null($value) && in_array($value, explode(',', $param ?? ''), true))
                        ? "{$label} must not be one of: {$param}."
                        : null,

        'alpha'     => (!is_null($value) && !ctype_alpha((string) $value))
                        ? "{$label} may only contain letters."
                        : null,

        'alpha_num' => (!is_null($value) && !ctype_alnum((string) $value))
                        ? "{$label} may only contain letters and numbers."
                        : null,

        'regex'     => (!is_null($value) && !preg_match($param, (string) $value))
                        ? "{$label} format is invalid."
                        : null,

        'date'      => (!is_null($value) && strtotime((string) $value) === false)
                        ? "{$label} must be a valid date."
                        : null,

        'array'     => (!is_null($value) && !is_array($value))
                        ? "{$label} must be an array."
                        : null,

        'unique'    => validation_rule_unique($label, $value, $param),

        default     => null,
    };
}

function validation_rule_min(string $label, mixed $value, int $min): ?string
{
    if (is_null($value)) return null;

    if (is_string($value) && mb_strlen($value) < $min) {
        return "{$label} must be at least {$min} characters.";
    }

    if (is_numeric($value) && (float) $value < $min) {
        return "{$label} must be at least {$min}.";
    }

    if (is_array($value) && count($value) < $min) {
        return "{$label} must have at least {$min} items.";
    }

    return null;
}

function validation_rule_max(string $label, mixed $value, int $max): ?string
{
    if (is_null($value)) return null;

    if (is_string($value) && mb_strlen($value) > $max) {
        return "{$label} may not exceed {$max} characters.";
    }

    if (is_numeric($value) && (float) $value > $max) {
        return "{$label} may not exceed {$max}.";
    }

    if (is_array($value) && count($value) > $max) {
        return "{$label} may not have more than {$max} items.";
    }

    return null;
}

function validation_rule_between(string $label, mixed $value, ?string $param): ?string
{
    if (is_null($value) || !$param) return null;

    [$min, $max] = array_map('trim', explode(',', $param));

    if (is_numeric($value)) {
        $v = (float) $value;
        if ($v < $min || $v > $max) {
            return "{$label} must be between {$min} and {$max}.";
        }
    }

    return null;
}

/**
 * Rule: unique:table,column
 * Checks that the value doesn't already exist in the DB.
 */
function validation_rule_unique(string $label, mixed $value, ?string $param): ?string
{
    if (is_null($value) || !$param) return null;

    $parts  = explode(',', $param);
    $table  = $parts[0];
    $column = $parts[1] ?? 'id';
    $except = $parts[2] ?? null; // ID to ignore (for updates)

    $query = db_table($table)->where($column, $value);

    if ($except) {
        $query->where('id', '!=', $except);
    }

    if ($query->exists()) {
        return "{$label} has already been taken.";
    }

    return null;
}

/**
 * Get validation errors from session (for views).
 */
function validation_errors(): array
{
    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);
    return $errors;
}

/**
 * Get the first error for a field.
 */
function validation_error(string $field): string
{
    $errors = $_SESSION['_errors'] ?? [];
    return $errors[$field][0] ?? '';
}

/**
 * Check if a field has an error.
 */
function has_error(string $field): bool
{
    return !empty($_SESSION['_errors'][$field]);
}

/**
 * Output Bootstrap-compatible invalid class if field has error.
 */
function error_class(string $field, string $class = 'is-invalid'): string
{
    return has_error($field) ? $class : '';
}
