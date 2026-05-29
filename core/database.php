<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════
//  FluxPHP — Database Layer (Phase 4)
//
//  Sections:
//    1. Connection manager  (multi-connection, lazy PDO)
//    2. Query execution     (query, select, first, value, execute)
//    3. Query logger        (debug log + query history)
//    4. CRUD helpers        (find, insert, update, delete, paginate)
//    5. Query Builder       (db_table() fluent builder)
//    6. Transactions        (db_transaction, savepoints)
//    7. Schema helpers      (db_schema_*)
//    8. Internal utilities  (build_where, timestamps, etc.)
// ═══════════════════════════════════════════════════════

// ─────────────────────────────────────────────────────
//  1. CONNECTION MANAGER
// ─────────────────────────────────────────────────────

$_FLUX_CONNECTIONS  = [];   // PDO instances keyed by name
$_FLUX_DB_DEFAULT   = null; // active connection name
$_FLUX_QUERY_LOG    = [];   // query history for debug

/**
 * Get (or lazy-create) a PDO connection by name.
 * db()           — default connection
 * db('reporting') — named connection
 */
function db(string $name = ''): PDO
{
    global $_FLUX_CONNECTIONS, $_FLUX_DB_DEFAULT;

    $name = $name ?: (config('database.default', 'mysql'));

    if (isset($_FLUX_CONNECTIONS[$name])) {
        return $_FLUX_CONNECTIONS[$name];
    }

    $cfg = config("database.connections.{$name}");

    if (!$cfg) {
        throw new \RuntimeException("Database connection [{$name}] not configured.");
    }

    $dsn = match ($cfg['driver']) {
        'mysql'  => sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset'] ?? 'utf8mb4'
        ),
        'pgsql'  => sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $cfg['host'], $cfg['port'], $cfg['database']
        ),
        'sqlite' => 'sqlite:' . $cfg['database'],
        default  => throw new \RuntimeException("Unsupported DB driver: {$cfg['driver']}"),
    };

    try {
        $pdo = new PDO(
            $dsn,
            $cfg['username'] ?? null,
            $cfg['password'] ?? null,
            $cfg['options']  ?? [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        $_FLUX_CONNECTIONS[$name] = $pdo;
        $_FLUX_DB_DEFAULT = $_FLUX_DB_DEFAULT ?? $name;

        log_debug("DB connected: [{$name}]");

    } catch (\PDOException $e) {
        log_critical("DB connection failed [{$name}]", ['error' => $e->getMessage()]);
        if (is_debug()) throw $e;
        abort(500, 'Database connection failed.');
    }

    return $_FLUX_CONNECTIONS[$name];
}

/**
 * Switch the default connection for subsequent calls.
 * db_use('reporting');
 */
function db_use(string $name): void
{
    global $_FLUX_DB_DEFAULT;
    $_FLUX_DB_DEFAULT = $name;
}

/**
 * Disconnect a connection (or all).
 */
function db_disconnect(string $name = ''): void
{
    global $_FLUX_CONNECTIONS;

    if ($name) {
        unset($_FLUX_CONNECTIONS[$name]);
    } else {
        $_FLUX_CONNECTIONS = [];
    }
}

// ─────────────────────────────────────────────────────
//  2. QUERY EXECUTION
// ─────────────────────────────────────────────────────

/**
 * Prepare and execute a raw SQL statement.
 * Returns the PDOStatement.
 */
function db_query(string $sql, array $bindings = [], string $connection = ''): \PDOStatement
{
    $pdo  = db($connection);
    $time = microtime(true);

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
    } catch (\PDOException $e) {
        log_error('DB query failed', [
            'sql'      => $sql,
            'bindings' => $bindings,
            'error'    => $e->getMessage(),
        ]);
        if (is_debug()) throw $e;
        abort(500, 'Database query failed.');
    }

    db_log_query($sql, $bindings, microtime(true) - $time);

    return $stmt;
}

/**
 * SELECT — returns array of rows.
 */
function db_select(string $sql, array $bindings = [], string $connection = ''): array
{
    return db_query($sql, $bindings, $connection)->fetchAll();
}

/**
 * SELECT first row or null.
 */
function db_first(string $sql, array $bindings = [], string $connection = ''): ?array
{
    $row = db_query($sql, $bindings, $connection)->fetch();
    return $row ?: null;
}

/**
 * SELECT single scalar value.
 */
function db_value(string $sql, array $bindings = [], string $connection = ''): mixed
{
    $val = db_query($sql, $bindings, $connection)->fetchColumn();
    return $val !== false ? $val : null;
}

/**
 * INSERT / UPDATE / DELETE — returns affected row count.
 */
function db_execute(string $sql, array $bindings = [], string $connection = ''): int
{
    return db_query($sql, $bindings, $connection)->rowCount();
}

/**
 * Run multiple SQL statements separated by semicolons.
 * Used internally by migrations.
 */
function db_unprepared(string $sql, string $connection = ''): void
{
    db($connection)->exec($sql);
    db_log_query($sql, [], 0);
}

// ─────────────────────────────────────────────────────
//  3. QUERY LOGGER
// ─────────────────────────────────────────────────────

function db_log_query(string $sql, array $bindings, float $time): void
{
    global $_FLUX_QUERY_LOG;

    $entry = [
        'sql'      => $sql,
        'bindings' => $bindings,
        'time_ms'  => round($time * 1000, 2),
    ];

    $_FLUX_QUERY_LOG[] = $entry;

    if (is_debug()) {
        log_debug('DB', $entry);
    }
}

/**
 * Get the full query log for the current request.
 */
function db_get_log(): array
{
    global $_FLUX_QUERY_LOG;
    return $_FLUX_QUERY_LOG;
}

/**
 * Clear the query log.
 */
function db_clear_log(): void
{
    global $_FLUX_QUERY_LOG;
    $_FLUX_QUERY_LOG = [];
}

/**
 * Get total query count for the current request.
 */
function db_query_count(): int
{
    global $_FLUX_QUERY_LOG;
    return count($_FLUX_QUERY_LOG);
}

/**
 * Get total query execution time in ms.
 */
function db_query_time(): float
{
    global $_FLUX_QUERY_LOG;
    return array_sum(array_column($_FLUX_QUERY_LOG, 'time_ms'));
}

// ─────────────────────────────────────────────────────
//  4. CRUD HELPERS
// ─────────────────────────────────────────────────────

function db_find(string $table, int|string $id, string $column = 'id'): ?array
{
    return db_first("SELECT * FROM `{$table}` WHERE `{$column}` = ? LIMIT 1", [$id]);
}

function db_find_where(string $table, array $conditions, string $columns = '*'): ?array
{
    [$where, $bindings] = db_build_where($conditions);
    return db_first("SELECT {$columns} FROM `{$table}` WHERE {$where} LIMIT 1", $bindings);
}

function db_find_or_fail(string $table, int|string $id, string $column = 'id'): array
{
    $row = db_find($table, $id, $column);
    if (!$row) abort(404, "Record not found in [{$table}].");
    return $row;
}

function db_all(string $table, string $columns = '*', string $orderBy = 'id ASC'): array
{
    return db_select("SELECT {$columns} FROM `{$table}` ORDER BY {$orderBy}");
}

function db_where(string $table, array $conditions, string $columns = '*', string $orderBy = ''): array
{
    [$where, $bindings] = db_build_where($conditions);
    $order = $orderBy ? " ORDER BY {$orderBy}" : '';
    return db_select("SELECT {$columns} FROM `{$table}` WHERE {$where}{$order}", $bindings);
}

function db_insert(string $table, array $data): string
{
    $data     = db_add_timestamps($data, create: true);
    $cols     = implode('`, `', array_keys($data));
    $holders  = implode(', ', array_fill(0, count($data), '?'));

    db_query("INSERT INTO `{$table}` (`{$cols}`) VALUES ({$holders})", array_values($data));

    return db()->lastInsertId();
}

function db_insert_many(string $table, array $rows): int
{
    if (empty($rows)) return 0;

    $rows     = array_map(fn($r) => db_add_timestamps($r, create: true), $rows);
    $cols     = implode('`, `', array_keys($rows[0]));
    $holders  = '(' . implode(', ', array_fill(0, count($rows[0]), '?')) . ')';
    $allHolders = implode(', ', array_fill(0, count($rows), $holders));
    $bindings = array_merge(...array_map('array_values', $rows));

    return db_execute("INSERT INTO `{$table}` (`{$cols}`) VALUES {$allHolders}", $bindings);
}

function db_update(string $table, int|string $id, array $data, string $column = 'id'): int
{
    $data     = db_add_timestamps($data, create: false);
    $set      = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
    $bindings = [...array_values($data), $id];

    return db_execute("UPDATE `{$table}` SET {$set} WHERE `{$column}` = ?", $bindings);
}

function db_update_where(string $table, array $conditions, array $data): int
{
    $data            = db_add_timestamps($data, create: false);
    $set             = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
    [$where, $wBind] = db_build_where($conditions);

    return db_execute(
        "UPDATE `{$table}` SET {$set} WHERE {$where}",
        [...array_values($data), ...$wBind]
    );
}

function db_upsert(string $table, array $data, array $uniqueKeys): string|int
{
    if (!empty($uniqueKeys)) {
        $conditions = arr_only($data, $uniqueKeys);
        $existing   = db_find_where($table, $conditions);
        if ($existing) {
            db_update($table, $existing['id'], arr_except($data, $uniqueKeys));
            return $existing['id'];
        }
    }
    return db_insert($table, $data);
}

function db_delete(string $table, int|string $id, string $column = 'id'): int
{
    return db_execute("DELETE FROM `{$table}` WHERE `{$column}` = ?", [$id]);
}

function db_delete_where(string $table, array $conditions): int
{
    [$where, $bindings] = db_build_where($conditions);
    return db_execute("DELETE FROM `{$table}` WHERE {$where}", $bindings);
}

function db_soft_delete(string $table, int|string $id, string $column = 'id'): int
{
    return db_update($table, $id, ['deleted_at' => date('Y-m-d H:i:s')], $column);
}

function db_restore(string $table, int|string $id, string $column = 'id'): int
{
    return db_update($table, $id, ['deleted_at' => null], $column);
}

function db_count(string $table, array $conditions = []): int
{
    if (empty($conditions)) {
        return (int) db_value("SELECT COUNT(*) FROM `{$table}`");
    }
    [$where, $bindings] = db_build_where($conditions);
    return (int) db_value("SELECT COUNT(*) FROM `{$table}` WHERE {$where}", $bindings);
}

function db_exists(string $table, array $conditions): bool
{
    return db_count($table, $conditions) > 0;
}

function db_max(string $table, string $column, array $conditions = []): mixed
{
    if (empty($conditions)) {
        return db_value("SELECT MAX(`{$column}`) FROM `{$table}`");
    }
    [$where, $bindings] = db_build_where($conditions);
    return db_value("SELECT MAX(`{$column}`) FROM `{$table}` WHERE {$where}", $bindings);
}

function db_min(string $table, string $column, array $conditions = []): mixed
{
    if (empty($conditions)) {
        return db_value("SELECT MIN(`{$column}`) FROM `{$table}`");
    }
    [$where, $bindings] = db_build_where($conditions);
    return db_value("SELECT MIN(`{$column}`) FROM `{$table}` WHERE {$where}", $bindings);
}

function db_sum(string $table, string $column, array $conditions = []): mixed
{
    if (empty($conditions)) {
        return db_value("SELECT SUM(`{$column}`) FROM `{$table}`");
    }
    [$where, $bindings] = db_build_where($conditions);
    return db_value("SELECT SUM(`{$column}`) FROM `{$table}` WHERE {$where}", $bindings);
}

function db_paginate(
    string $table,
    int    $page       = 1,
    int    $perPage    = 15,
    array  $conditions = [],
    string $columns    = '*',
    string $orderBy    = 'id ASC'
): array {
    $page   = max(1, $page);
    $offset = ($page - 1) * $perPage;
    $total  = db_count($table, $conditions);

    if (!empty($conditions)) {
        [$where, $bindings] = db_build_where($conditions);
        $rows = db_select(
            "SELECT {$columns} FROM `{$table}` WHERE {$where} ORDER BY {$orderBy} LIMIT ? OFFSET ?",
            [...$bindings, $perPage, $offset]
        );
    } else {
        $rows = db_select(
            "SELECT {$columns} FROM `{$table}` ORDER BY {$orderBy} LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
    }

    return [
        'data'         => $rows,
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $page,
        'last_page'    => max(1, (int) ceil($total / $perPage)),
        'from'         => $total > 0 ? $offset + 1 : 0,
        'to'           => min($offset + $perPage, $total),
        'has_more'     => $page < (int) ceil($total / $perPage),
    ];
}

// ─────────────────────────────────────────────────────
//  5. QUERY BUILDER
// ─────────────────────────────────────────────────────

/**
 * Start a fluent query builder on a table.
 *
 * $users = db_table('users')
 *     ->select('id, name, email')
 *     ->where('active', 1)
 *     ->where('role', 'admin')
 *     ->order('name ASC')
 *     ->limit(10)
 *     ->get();
 *
 * $user = db_table('users')->where('email', $email)->first();
 *
 * db_table('users')->where('id', $id)->update(['name' => 'New']);
 * db_table('users')->where('id', $id)->delete();
 */
function db_table(string $table, string $connection = ''): FluxQueryBuilder
{
    return new FluxQueryBuilder($table, $connection);
}

class FluxQueryBuilder
{
    private string $table;
    private string $connection;
    private string $columns    = '*';
    private array  $wheres     = [];
    private array  $bindings   = [];
    private array  $orWheres   = [];
    private array  $joins      = [];
    private string $orderBy    = '';
    private string $groupBy    = '';
    private string $having     = '';
    private ?int   $limitVal   = null;
    private ?int   $offsetVal  = null;
    private bool   $withTrashed = false;

    public function __construct(string $table, string $connection = '')
    {
        $this->table      = $table;
        $this->connection = $connection;
    }

    // ── Column selection ──────────────────────

    public function select(string $columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    // ── WHERE conditions ──────────────────────

    /**
     * Add a WHERE condition.
     * ->where('status', 'active')
     * ->where('age', '>', 18)
     * ->where('deleted_at', null)     — IS NULL
     */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        if ($value === null && $operatorOrValue === null) {
            $this->wheres[]   = "`{$column}` IS NULL";
            return $this;
        }

        if ($value === null) {
            // ->where('col', 'val') shorthand
            $this->wheres[]   = "`{$column}` = ?";
            $this->bindings[] = $operatorOrValue;
        } else {
            // ->where('col', '>', val)
            $op = strtoupper(trim((string) $operatorOrValue));
            $this->wheres[]   = "`{$column}` {$op} ?";
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = "`{$column}` IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = "`{$column}` IS NOT NULL";
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        if (empty($values)) {
            $this->wheres[] = '1 = 0'; // no results
            return $this;
        }
        $holders          = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[]   = "`{$column}` IN ({$holders})";
        $this->bindings   = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        if (empty($values)) return $this;
        $holders          = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[]   = "`{$column}` NOT IN ({$holders})";
        $this->bindings   = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereLike(string $column, string $value): static
    {
        $this->wheres[]   = "`{$column}` LIKE ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        $this->wheres[]   = "`{$column}` BETWEEN ? AND ?";
        $this->bindings[] = $min;
        $this->bindings[] = $max;
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = []): static
    {
        $this->wheres[]  = $sql;
        $this->bindings  = array_merge($this->bindings, $bindings);
        return $this;
    }

    // ── JOINs ─────────────────────────────────

    public function join(string $table, string $on, string $type = 'INNER'): static
    {
        $this->joins[] = "{$type} JOIN `{$table}` ON {$on}";
        return $this;
    }

    public function leftJoin(string $table, string $on): static
    {
        return $this->join($table, $on, 'LEFT');
    }

    public function rightJoin(string $table, string $on): static
    {
        return $this->join($table, $on, 'RIGHT');
    }

    // ── ORDER / GROUP / LIMIT ─────────────────

    public function order(string $column, string $dir = 'ASC'): static
    {
        $this->orderBy = "`{$column}` {$dir}";
        return $this;
    }

    public function orderRaw(string $raw): static
    {
        $this->orderBy = $raw;
        return $this;
    }

    public function groupBy(string $column): static
    {
        $this->groupBy = "`{$column}`";
        return $this;
    }

    public function having(string $raw): static
    {
        $this->having = $raw;
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limitVal = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offsetVal = $offset;
        return $this;
    }

    public function withTrashed(): static
    {
        $this->withTrashed = true;
        return $this;
    }

    // ── RESULT METHODS ────────────────────────

    public function get(): array
    {
        [$sql, $bindings] = $this->buildSelect();
        return db_select($sql, $bindings, $this->connection);
    }

    public function first(): ?array
    {
        $this->limitVal = 1;
        [$sql, $bindings] = $this->buildSelect();
        return db_first($sql, $bindings, $this->connection);
    }

    public function firstOrFail(): array
    {
        $row = $this->first();
        if (!$row) abort(404, "Record not found in [{$this->table}].");
        return $row;
    }

    public function value(string $column): mixed
    {
        $this->columns  = $column;
        $this->limitVal = 1;
        [$sql, $bindings] = $this->buildSelect();
        return db_value($sql, $bindings, $this->connection);
    }

    public function count(): int
    {
        $this->columns = 'COUNT(*) as aggregate';
        [$sql, $bindings] = $this->buildSelect();
        return (int) db_value($sql, $bindings, $this->connection);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function sum(string $column): mixed
    {
        $this->columns = "SUM(`{$column}`) as aggregate";
        [$sql, $bindings] = $this->buildSelect();
        return db_value($sql, $bindings, $this->connection);
    }

    public function max(string $column): mixed
    {
        $this->columns = "MAX(`{$column}`) as aggregate";
        [$sql, $bindings] = $this->buildSelect();
        return db_value($sql, $bindings, $this->connection);
    }

    public function min(string $column): mixed
    {
        $this->columns = "MIN(`{$column}`) as aggregate";
        [$sql, $bindings] = $this->buildSelect();
        return db_value($sql, $bindings, $this->connection);
    }

    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $total = $this->count();

        $page        = max(1, $page);
        $offset      = ($page - 1) * $perPage;
        $this->limit($perPage)->offset($offset);
        $this->columns = '*';

        [$sql, $bindings] = $this->buildSelect();
        $rows = db_select($sql, $bindings, $this->connection);

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
            'from'         => $total > 0 ? $offset + 1 : 0,
            'to'           => min($offset + $perPage, $total),
            'has_more'     => $page < (int) ceil($total / $perPage),
        ];
    }

    // ── WRITE METHODS ─────────────────────────

    public function insert(array $data): string
    {
        return db_insert($this->table, $data);
    }

    public function update(array $data): int
    {
        $data            = db_add_timestamps($data, create: false);
        $set             = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        [$where, $wBind] = $this->buildWhere();
        $sql             = "UPDATE `{$this->table}` SET {$set}" . ($where ? " WHERE {$where}" : '');

        return db_execute($sql, [...array_values($data), ...$wBind], $this->connection);
    }

    public function delete(): int
    {
        [$where, $wBind] = $this->buildWhere();
        $sql = "DELETE FROM `{$this->table}`" . ($where ? " WHERE {$where}" : '');
        return db_execute($sql, $wBind, $this->connection);
    }

    public function softDelete(): int
    {
        return $this->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    // ── SQL BUILDER ───────────────────────────

    public function toSql(): string
    {
        [$sql] = $this->buildSelect();
        return $sql;
    }

    private function buildSelect(): array
    {
        [$where, $bindings] = $this->buildWhere();

        $sql = "SELECT {$this->columns} FROM `{$this->table}`";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if ($where) {
            $sql .= " WHERE {$where}";
        }

        if ($this->groupBy) {
            $sql .= " GROUP BY {$this->groupBy}";
        }

        if ($this->having) {
            $sql .= " HAVING {$this->having}";
        }

        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }

        if ($this->limitVal !== null) {
            $sql .= " LIMIT {$this->limitVal}";
        }

        if ($this->offsetVal !== null) {
            $sql .= " OFFSET {$this->offsetVal}";
        }

        return [$sql, $bindings];
    }

    private function buildWhere(): array
    {
        $parts    = $this->wheres;
        $bindings = $this->bindings;

        // Auto-exclude soft-deleted unless withTrashed()
        if (!$this->withTrashed) {
            $cols = db_schema_columns($this->table);
            if (in_array('deleted_at', $cols, true)) {
                $parts[] = "`{$this->table}`.`deleted_at` IS NULL";
            }
        }

        return [
            empty($parts) ? '' : implode(' AND ', $parts),
            $bindings,
        ];
    }
}

// ─────────────────────────────────────────────────────
//  6. TRANSACTIONS
// ─────────────────────────────────────────────────────

/**
 * Run a callback inside a database transaction.
 * Rolls back automatically on any exception.
 *
 * $id = db_transaction(function() use ($data) {
 *     $id = db_insert('orders', $data);
 *     db_insert('order_items', [...]);
 *     return $id;
 * });
 */
function db_transaction(callable $callback, string $connection = ''): mixed
{
    $pdo = db($connection);
    $pdo->beginTransaction();

    try {
        $result = $callback();
        $pdo->commit();
        return $result;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        log_error('DB transaction rolled back', ['error' => $e->getMessage()]);
        throw $e;
    }
}

/**
 * Create a savepoint.
 */
function db_savepoint(string $name, string $connection = ''): void
{
    db_execute("SAVEPOINT {$name}", [], $connection);
}

/**
 * Release a savepoint.
 */
function db_release(string $name, string $connection = ''): void
{
    db_execute("RELEASE SAVEPOINT {$name}", [], $connection);
}

/**
 * Roll back to a savepoint.
 */
function db_rollback_to(string $name, string $connection = ''): void
{
    db_execute("ROLLBACK TO SAVEPOINT {$name}", [], $connection);
}

// ─────────────────────────────────────────────────────
//  7. SCHEMA HELPERS
// ─────────────────────────────────────────────────────

$_FLUX_SCHEMA_CACHE = [];

/**
 * Check if a table exists.
 */
function db_schema_has_table(string $table): bool
{
    $result = db_value(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?",
        [$table]
    );
    return (int) $result > 0;
}

/**
 * Get column names for a table (cached per request).
 */
function db_schema_columns(string $table): array
{
    global $_FLUX_SCHEMA_CACHE;

    if (isset($_FLUX_SCHEMA_CACHE[$table])) {
        return $_FLUX_SCHEMA_CACHE[$table];
    }

    try {
        $rows = db_select("SHOW COLUMNS FROM `{$table}`");
        $cols = array_column($rows, 'Field');
    } catch (\Throwable) {
        $cols = [];
    }

    $_FLUX_SCHEMA_CACHE[$table] = $cols;
    return $cols;
}

/**
 * Check if a column exists in a table.
 */
function db_schema_has_column(string $table, string $column): bool
{
    return in_array($column, db_schema_columns($table), true);
}

/**
 * Drop a table if it exists.
 */
function db_schema_drop(string $table): void
{
    db_execute("DROP TABLE IF EXISTS `{$table}`");
}

/**
 * Truncate a table.
 */
function db_schema_truncate(string $table): void
{
    db_execute("TRUNCATE TABLE `{$table}`");
}

/**
 * List all tables in the current database.
 */
function db_schema_tables(): array
{
    $rows = db_select("SHOW TABLES");
    return array_map('reset', $rows);
}

// ─────────────────────────────────────────────────────
//  8. INTERNAL UTILITIES
// ─────────────────────────────────────────────────────

/**
 * Build a WHERE clause from a conditions array.
 * Supports: =, IS NULL, and raw strings.
 */
function db_build_where(array $conditions): array
{
    $parts    = [];
    $bindings = [];

    foreach ($conditions as $key => $value) {
        if (is_int($key)) {
            // Raw string condition: ['deleted_at IS NULL']
            $parts[] = $value;
        } elseif ($value === null) {
            $parts[] = "`{$key}` IS NULL";
        } elseif (is_array($value)) {
            // ['status' => ['active', 'pending']]  →  IN (?,?)
            $holders  = implode(', ', array_fill(0, count($value), '?'));
            $parts[]  = "`{$key}` IN ({$holders})";
            $bindings = array_merge($bindings, $value);
        } else {
            $parts[]    = "`{$key}` = ?";
            $bindings[] = $value;
        }
    }

    return [implode(' AND ', $parts), $bindings];
}

/**
 * Automatically inject created_at / updated_at timestamps.
 * Only added if the key doesn't already exist in $data.
 */
function db_add_timestamps(array $data, bool $create = false): array
{
    $now = date('Y-m-d H:i:s');

    if ($create && !isset($data['created_at'])) {
        $data['created_at'] = $now;
    }

    if (!isset($data['updated_at'])) {
        $data['updated_at'] = $now;
    }

    return $data;
}

/**
 * Escape an identifier (table/column name) safely.
 */
function db_identifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}
