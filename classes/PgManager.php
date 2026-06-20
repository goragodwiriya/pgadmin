<?php

declare(strict_types=1);

class PgManager
{
    public function __construct(private readonly Database $db) {}

    /* ── Server Info ── */

    public function getServerInfo(): array
    {
        return [
            'version' => $this->db->fetchValue('SELECT version()'),
            'current_database' => $this->db->fetchValue('SELECT current_database()'),
            'current_user' => $this->db->fetchValue('SELECT current_user'),
            'server_encoding' => $this->db->fetchValue('SHOW server_encoding'),
            'client_encoding' => $this->db->fetchValue('SHOW client_encoding'),
            'timezone' => $this->db->fetchValue('SHOW timezone'),
            'max_connections' => $this->db->fetchValue('SHOW max_connections'),
            'active_connections' => $this->db->fetchValue(
                "SELECT count(*) FROM pg_stat_activity WHERE state = 'active'"
            ),
        ];
    }

    /* ── Databases ── */

    public function listDatabases(): array
    {
        return $this->db->fetchAll("
            SELECT d.datname AS name,
                   pg_catalog.pg_get_userbyid(d.datdba) AS owner,
                   pg_catalog.pg_encoding_to_char(d.encoding) AS encoding,
                   pg_catalog.pg_size_pretty(pg_catalog.pg_database_size(d.datname)) AS size,
                   d.datcollate AS collate,
                   d.datctype AS ctype,
                   (SELECT count(*) FROM pg_stat_activity WHERE datname = d.datname) AS connections
            FROM pg_catalog.pg_database d
            WHERE d.datistemplate = false
            ORDER BY d.datname
        ");
    }

    public function createDatabase(string $name, string $owner = '', string $encoding = 'UTF8'): void
    {
        $name = $this->validateIdentifier($name);
        $sql = "CREATE DATABASE {$this->q($name)} ENCODING " . $this->q($encoding);
        if ($owner !== '') {
            $sql .= ' OWNER ' . $this->q($this->validateIdentifier($owner));
        }
        $this->db->execute($sql);
    }

    public function dropDatabase(string $name): void
    {
        $name = $this->validateIdentifier($name);
        $this->db->execute("DROP DATABASE {$this->q($name)}");
    }

    /* ── Schemas ── */

    public function listSchemas(): array
    {
        return $this->db->fetchAll("
            SELECT schema_name AS name,
                   schema_owner AS owner
            FROM information_schema.schemata
            WHERE schema_name NOT IN ('pg_toast', 'pg_temp_1', 'pg_toast_temp_1')
              AND schema_name NOT LIKE 'pg_temp_%'
              AND schema_name NOT LIKE 'pg_toast_temp_%'
            ORDER BY schema_name
        ");
    }

    public function createSchema(string $name, string $owner = ''): void
    {
        $name = $this->validateIdentifier($name);
        $sql = "CREATE SCHEMA {$this->q($name)}";
        if ($owner !== '') {
            $sql .= ' AUTHORIZATION ' . $this->q($this->validateIdentifier($owner));
        }
        $this->db->execute($sql);
    }

    public function dropSchema(string $name, bool $cascade = false): void
    {
        $name = $this->validateIdentifier($name);
        $cascadeStr = $cascade ? ' CASCADE' : '';
        $this->db->execute("DROP SCHEMA {$this->q($name)}{$cascadeStr}");
    }

    /* ── Tables ── */

    public function listTables(?string $schema = null): array
    {
        $params = [];
        $where = "t.table_type = 'BASE TABLE' AND t.table_schema NOT IN ('pg_catalog', 'information_schema')";

        if ($schema !== null) {
            $where .= ' AND t.table_schema = :schema';
            $params['schema'] = $schema;
        }

        return $this->db->fetchAll("
            SELECT t.table_schema AS schema,
                   t.table_name AS name,
                   pg_catalog.pg_size_pretty(
                       pg_catalog.pg_total_relation_size(
                           quote_ident(t.table_schema) || '.' || quote_ident(t.table_name)
                       )
                   ) AS size,
                   (SELECT GREATEST(0, reltuples)::bigint FROM pg_catalog.pg_class c
                    JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                    WHERE c.relname = t.table_name AND n.nspname = t.table_schema
                    LIMIT 1) AS estimated_rows,
                   obj_description(
                       (quote_ident(t.table_schema) || '.' || quote_ident(t.table_name))::regclass
                   ) AS comment
            FROM information_schema.tables t
            WHERE {$where}
            ORDER BY t.table_schema, t.table_name
        ", $params);
    }

    public function listViews(?string $schema = null): array
    {
        $params = [];
        $where = "t.table_type = 'VIEW' AND t.table_schema NOT IN ('pg_catalog', 'information_schema')";

        if ($schema !== null) {
            $where .= ' AND t.table_schema = :schema';
            $params['schema'] = $schema;
        }

        return $this->db->fetchAll("
            SELECT t.table_schema AS schema, t.table_name AS name
            FROM information_schema.tables t
            WHERE {$where}
            ORDER BY t.table_schema, t.table_name
        ", $params);
    }

    public function getTableStructure(string $schema, string $table): array
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);

        $columns = $this->db->fetchAll("
            SELECT c.column_name AS name,
                   c.data_type AS type,
                   c.character_maximum_length AS max_length,
                   c.numeric_precision AS precision,
                   c.numeric_scale AS scale,
                   c.is_nullable AS nullable,
                   c.column_default AS default_value,
                   CASE WHEN pk.column_name IS NOT NULL THEN true ELSE false END AS is_primary_key
            FROM information_schema.columns c
            LEFT JOIN (
                SELECT kcu.column_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu
                  ON tc.constraint_name = kcu.constraint_name
                 AND tc.table_schema = kcu.table_schema
                WHERE tc.constraint_type = 'PRIMARY KEY'
                  AND tc.table_schema = :schema1
                  AND tc.table_name = :table1
            ) pk ON pk.column_name = c.column_name
            WHERE c.table_schema = :schema2 AND c.table_name = :table2
            ORDER BY c.ordinal_position
        ", [
            'schema1' => $schema, 'table1' => $table,
            'schema2' => $schema, 'table2' => $table,
        ]);

        $indexes = $this->db->fetchAll("
            SELECT indexname AS name,
                   indexdef AS definition
            FROM pg_indexes
            WHERE schemaname = :schema AND tablename = :table
            ORDER BY indexname
        ", ['schema' => $schema, 'table' => $table]);

        $constraints = $this->db->fetchAll("
            SELECT tc.constraint_name AS name,
                   tc.constraint_type AS type,
                   kcu.column_name AS column_name,
                   ccu.table_schema AS foreign_schema,
                   ccu.table_name AS foreign_table,
                   ccu.column_name AS foreign_column
            FROM information_schema.table_constraints tc
            LEFT JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name
             AND tc.table_schema = kcu.table_schema
            LEFT JOIN information_schema.constraint_column_usage ccu
              ON tc.constraint_name = ccu.constraint_name
             AND tc.table_schema = ccu.table_schema
            WHERE tc.table_schema = :schema AND tc.table_name = :table
            ORDER BY tc.constraint_type, tc.constraint_name
        ", ['schema' => $schema, 'table' => $table]);

        foreach ($columns as &$col) {
            $col['input_type'] = $this->resolveInputType($col['type']);
            $col['full_type'] = $this->formatColumnType($col);
        }
        unset($col);

        return compact('columns', 'indexes', 'constraints');
    }

    public function getTableColumnMeta(string $schema, string $table): array
    {
        $structure = $this->getTableStructure($schema, $table);
        $meta = [];
        foreach ($structure['columns'] as $col) {
            $meta[$col['name']] = $col;
        }
        return $meta;
    }

    public function addColumn(
        string $schema,
        string $table,
        string $columnName,
        string $type,
        bool $notNull = false,
        ?string $default = null
    ): void {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $columnName = $this->validateIdentifier($columnName);
        $type = $this->validateType($type);

        $sql = sprintf(
            'ALTER TABLE %s.%s ADD COLUMN %s %s',
            $this->q($schema),
            $this->q($table),
            $this->q($columnName),
            $type
        );
        if ($notNull) {
            $sql .= ' NOT NULL';
        }
        if ($default !== null && $default !== '') {
            $sql .= ' DEFAULT ' . $this->normalizeDefaultExpression($default);
        }
        $this->db->execute($sql);
    }

    public function dropColumn(string $schema, string $table, string $columnName): void
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $columnName = $this->validateIdentifier($columnName);

        $this->db->execute(sprintf(
            'ALTER TABLE %s.%s DROP COLUMN %s',
            $this->q($schema),
            $this->q($table),
            $this->q($columnName)
        ));
    }

    public function alterColumn(string $schema, string $table, string $columnName, array $changes): void
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $columnName = $this->validateIdentifier($columnName);
        $qualified = "{$this->q($schema)}.{$this->q($table)}";

        if (!empty($changes['new_name'])) {
            $newName = $this->validateIdentifier($changes['new_name']);
            $this->db->execute(sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $qualified,
                $this->q($columnName),
                $this->q($newName)
            ));
            $columnName = $newName;
        }

        if (!empty($changes['type'])) {
            $type = $this->validateType($changes['type']);
            $this->db->execute(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE %s',
                $qualified,
                $this->q($columnName),
                $type
            ));
        }

        if (!empty($changes['drop_default'])) {
            $this->db->execute(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT',
                $qualified,
                $this->q($columnName)
            ));
        } elseif (array_key_exists('default', $changes) && $changes['default'] !== null) {
            $this->db->execute(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s SET DEFAULT %s',
                $qualified,
                $this->q($columnName),
                $this->normalizeDefaultExpression((string) $changes['default'])
            ));
        }

        if ($changes['not_null'] === true) {
            $this->db->execute(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s SET NOT NULL',
                $qualified,
                $this->q($columnName)
            ));
        } elseif ($changes['not_null'] === false) {
            $this->db->execute(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s DROP NOT NULL',
                $qualified,
                $this->q($columnName)
            ));
        }
    }

    private function resolveInputType(string $dataType): string
    {
        return match ($dataType) {
            'date' => 'date',
            'timestamp without time zone', 'timestamp with time zone' => 'datetime',
            'time without time zone', 'time with time zone' => 'time',
            'boolean' => 'boolean',
            'integer', 'bigint', 'smallint', 'numeric', 'decimal', 'real', 'double precision' => 'number',
            'json', 'jsonb' => 'json',
            'text', 'character varying', 'character' => 'text',
            default => 'string',
        };
    }

    private function formatColumnType(array $col): string
    {
        $type = $col['type'];
        if ($col['max_length']) {
            return $type . '(' . $col['max_length'] . ')';
        }
        if ($col['precision'] && in_array($type, ['numeric', 'decimal'], true)) {
            return $type . '(' . $col['precision'] . ',' . ($col['scale'] ?? 0) . ')';
        }
        return $type;
    }

    private function validateType(string $type): string
    {
        $type = trim($type);
        if (!preg_match('/^[A-Za-z0-9_(),.\s]+$/', $type)) {
            throw new InvalidArgumentException('ชนิดข้อมูลไม่ถูกต้อง');
        }
        return $type;
    }

    public function createTable(string $schema, string $table, array $columns): void
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);

        if (empty($columns)) {
            throw new InvalidArgumentException('ต้องระบุคอลัมน์อย่างน้อย 1 คอลัมน์');
        }

        $defs = [];
        foreach ($columns as $col) {
            $name = $this->validateIdentifier($col['name']);
            $type = $col['type'];
            $def = $this->q($name) . ' ' . $type;
            if (!empty($col['not_null'])) {
                $def .= ' NOT NULL';
            }
            if (isset($col['default']) && $col['default'] !== '') {
                $def .= ' DEFAULT ' . $col['default'];
            }
            if (!empty($col['primary_key'])) {
                $def .= ' PRIMARY KEY';
            }
            $defs[] = $def;
        }

        $sql = sprintf(
            'CREATE TABLE %s.%s (%s)',
            $this->q($schema),
            $this->q($table),
            implode(', ', $defs)
        );
        $this->db->execute($sql);
    }

    public function dropTable(string $schema, string $table, bool $cascade = false): void
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $cascadeStr = $cascade ? ' CASCADE' : '';
        $this->db->execute("DROP TABLE {$this->q($schema)}.{$this->q($table)}{$cascadeStr}");
    }

    public function truncateTable(string $schema, string $table): void
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $this->db->execute("TRUNCATE TABLE {$this->q($schema)}.{$this->q($table)} RESTART IDENTITY");
    }

    public function analyzeTable(string $schema, string $table): void
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $this->db->execute(sprintf('ANALYZE %s.%s', $this->q($schema), $this->q($table)));
    }

    public function analyzeDatabase(): void
    {
        $this->db->execute('ANALYZE');
    }

    /* ── Table Data ── */

    public function getTableData(
        string $schema,
        string $table,
        int $page = 1,
        int $perPage = 50,
        ?string $orderBy = null,
        string $orderDir = 'ASC',
        ?string $search = null,
        ?string $searchColumn = null
    ): array {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $qualified = "{$this->q($schema)}.{$this->q($table)}";

        $where = '';
        $params = [];

        if ($search !== null && $search !== '') {
            if ($searchColumn !== null && $searchColumn !== '') {
                $searchColumn = $this->validateIdentifier($searchColumn);
                $where = "WHERE CAST({$this->q($searchColumn)} AS TEXT) ILIKE :search";
            } else {
                $cols = $this->getColumnNames($schema, $table);
                if (!empty($cols)) {
                    $conditions = array_map(
                        fn($c) => "CAST({$this->q($c)} AS TEXT) ILIKE :search",
                        $cols
                    );
                    $where = 'WHERE (' . implode(' OR ', $conditions) . ')';
                }
            }
            $params['search'] = '%' . $search . '%';
        }

        $total = (int) $this->db->fetchValue(
            "SELECT count(*) FROM {$qualified} {$where}",
            $params
        );

        $orderClause = '';
        if ($orderBy !== null) {
            $orderBy = $this->validateIdentifier($orderBy);
            $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
            $orderClause = "ORDER BY {$this->q($orderBy)} {$orderDir}";
        }

        $offset = ($page - 1) * $perPage;
        $params['limit'] = $perPage;
        $params['offset'] = $offset;

        $rows = $this->db->fetchAll(
            "SELECT * FROM {$qualified} {$where} {$orderClause} LIMIT :limit OFFSET :offset",
            $params
        );

        $columns = $this->getColumnNames($schema, $table);

        return [
            'rows' => $rows,
            'columns' => $columns,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function insertRow(string $schema, string $table, array $data): int
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);

        $columns = array_keys($data);
        foreach ($columns as $col) {
            $this->validateIdentifier($col);
        }

        $quotedCols = array_map(fn($c) => $this->q($c), $columns);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s.%s (%s) VALUES (%s)',
            $this->q($schema),
            $this->q($table),
            implode(', ', $quotedCols),
            implode(', ', $placeholders)
        );

        return $this->db->execute($sql, $data);
    }

    public function updateRow(string $schema, string $table, array $data, array $where, array $whereNull = []): int
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);

        $meta = $this->getTableColumnMeta($schema, $table);
        foreach ($data as $col => &$val) {
            if ($val === '') {
                $colMeta = $meta[$col] ?? null;
                if ($colMeta) {
                    $isString = in_array($colMeta['type'] ?? '', ['text', 'character varying', 'character'], true);
                    if (!$isString || ($colMeta['nullable'] ?? 'YES') === 'YES') {
                        $val = null;
                    }
                }
            }
        }
        unset($val);

        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $this->validateIdentifier($col);
            $sets[] = $this->q($col) . ' = :set_' . $col;
            $params['set_' . $col] = $val;
        }

        $conditions = [];
        foreach ($where as $col => $val) {
            $this->validateIdentifier($col);
            $conditions[] = $this->q($col) . ' = :where_' . $col;
            $params['where_' . $col] = $val;
        }
        foreach ($whereNull as $col) {
            $this->validateIdentifier($col);
            $conditions[] = $this->q($col) . ' IS NULL';
        }

        if (empty($conditions)) {
            throw new InvalidArgumentException('ไม่มีเงื่อนไขสำหรับการแก้ไขข้อมูล');
        }

        $sql = sprintf(
            'UPDATE %s.%s SET %s WHERE %s',
            $this->q($schema),
            $this->q($table),
            implode(', ', $sets),
            implode(' AND ', $conditions)
        );

        return $this->db->execute($sql, $params);
    }

    public function deleteRow(string $schema, string $table, array $where, array $whereNull = []): int
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);

        $conditions = [];
        $params = [];
        foreach ($where as $col => $val) {
            $this->validateIdentifier($col);
            $conditions[] = $this->q($col) . ' = :' . $col;
            $params[$col] = $val;
        }
        foreach ($whereNull as $col) {
            $this->validateIdentifier($col);
            $conditions[] = $this->q($col) . ' IS NULL';
        }

        if (empty($conditions)) {
            throw new InvalidArgumentException('ไม่มีเงื่อนไขสำหรับการลบข้อมูล');
        }

        $sql = sprintf(
            'DELETE FROM %s.%s WHERE %s',
            $this->q($schema),
            $this->q($table),
            implode(' AND ', $conditions)
        );

        return $this->db->execute($sql, $params);
    }

    /* ── SQL Query ── */

    public function executeQuery(string $sql, int $maxRows = 1000): array
    {
        if (isReadOnly() && !isReadOnlySql($sql)) {
            throw new RuntimeException(__('error.read_only_sql'));
        }

        $start = microtime(true);
        $stmt = $this->db->query($sql);
        $elapsed = round((microtime(true) - $start) * 1000, 2);

        if ($stmt->columnCount() > 0) {
            $columns = [];
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $meta = $stmt->getColumnMeta($i);
                $columns[] = $meta['name'] ?? "col_{$i}";
            }
            $rows = $stmt->fetchAll();
            $truncated = count($rows) > $maxRows;
            if ($truncated) {
                $rows = array_slice($rows, 0, $maxRows);
            }

            return [
                'type' => 'select',
                'columns' => $columns,
                'rows' => $rows,
                'row_count' => count($rows),
                'affected_rows' => count($rows),
                'truncated' => $truncated,
                'elapsed_ms' => $elapsed,
            ];
        }

        return [
            'type' => 'command',
            'affected_rows' => $stmt->rowCount(),
            'elapsed_ms' => $elapsed,
        ];
    }

    /* ── Export ── */

    public function exportTableSql(string $schema, string $table): string
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $structure = $this->getTableStructure($schema, $table);

        $output = "-- Export: {$schema}.{$table}\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        $cols = array_map(function ($c) {
            $def = $this->q($c['name']) . ' ' . $c['type'];
            if ($c['max_length']) {
                $def .= '(' . $c['max_length'] . ')';
            } elseif ($c['precision'] && in_array($c['type'], ['numeric', 'decimal'])) {
                $def .= '(' . $c['precision'] . ',' . ($c['scale'] ?? 0) . ')';
            }
            if ($c['nullable'] === 'NO') {
                $def .= ' NOT NULL';
            }
            if ($c['default_value']) {
                $def .= ' DEFAULT ' . $c['default_value'];
            }
            return $def;
        }, $structure['columns']);
        $output .= "CREATE TABLE {$this->q($schema)}.{$this->q($table)} (\n  ";
        $output .= implode(",\n  ", $cols) . "\n);\n\n";

        $data = $this->db->fetchAll("SELECT * FROM {$this->q($schema)}.{$this->q($table)}");
        if (!empty($data)) {
            $colNames = array_keys($data[0]);
            $quotedCols = array_map(fn($c) => $this->q($c), $colNames);

            foreach ($data as $row) {
                $values = array_map(fn($v) => $this->sqlValue($v), array_values($row));
                $output .= sprintf(
                    "INSERT INTO %s.%s (%s) VALUES (%s);\n",
                    $this->q($schema),
                    $this->q($table),
                    implode(', ', $quotedCols),
                    implode(', ', $values)
                );
            }
        }

        return $output;
    }

    public function exportTableCsv(string $schema, string $table): string
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $data = $this->db->fetchAll("SELECT * FROM {$this->q($schema)}.{$this->q($table)}");

        $output = fopen('php://temp', 'r+');
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv ?: '';
    }

    public function exportTableJson(string $schema, string $table): string
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $data = $this->db->fetchAll("SELECT * FROM {$this->q($schema)}.{$this->q($table)}");

        return json_encode([
            'schema' => $schema,
            'table' => $table,
            'exported_at' => date('c'),
            'row_count' => count($data),
            'rows' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function exportDatabaseSql(?string $database = null): string
    {
        $dbName = $database ?? $this->db->getDatabase();
        $output = "-- Backup: {$dbName}\n";
        $output .= '-- Generated: ' . date('Y-m-d H:i:s') . "\n\n";

        $schemas = $this->db->fetchAll("
            SELECT schema_name AS name
            FROM information_schema.schemata
            WHERE schema_name NOT IN ('pg_catalog', 'information_schema', 'pg_toast')
              AND schema_name NOT LIKE 'pg_temp_%'
              AND schema_name NOT LIKE 'pg_toast_temp_%'
            ORDER BY schema_name
        ");

        foreach ($schemas as $schemaRow) {
            $schema = $schemaRow['name'];
            if ($schema !== 'public') {
                $output .= "CREATE SCHEMA IF NOT EXISTS {$this->q($schema)};\n\n";
            }

            $tables = $this->listTables($schema);
            foreach ($tables as $tableRow) {
                $output .= $this->exportTableSql($tableRow['schema'], $tableRow['name']) . "\n";
            }
        }

        return $output;
    }

    public function addIndex(
        string $schema,
        string $table,
        string $indexName,
        array $columns,
        bool $unique = false
    ): void {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $indexName = $this->validateIdentifier($indexName);

        if (empty($columns)) {
            throw new InvalidArgumentException('ต้องระบุคอลัมน์อย่างน้อย 1 คอลัมน์');
        }

        $quotedCols = array_map(fn(string $c) => $this->q($this->validateIdentifier($c)), $columns);
        $uniqueStr = $unique ? 'UNIQUE ' : '';

        $this->db->execute(sprintf(
            'CREATE %sINDEX %s ON %s.%s (%s)',
            $uniqueStr,
            $this->q($indexName),
            $this->q($schema),
            $this->q($table),
            implode(', ', $quotedCols)
        ));
    }

    public function dropIndex(string $schema, string $indexName): void
    {
        $schema = $this->validateIdentifier($schema);
        $indexName = $this->validateIdentifier($indexName);
        $this->db->execute("DROP INDEX {$this->q($schema)}.{$this->q($indexName)}");
    }

    public function addPrimaryKey(string $schema, string $table, array $columns, ?string $name = null): void
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $constraint = $name !== null && $name !== ''
            ? $this->q($this->validateIdentifier($name))
            : '';

        if (empty($columns)) {
            throw new InvalidArgumentException('ต้องระบุคอลัมน์อย่างน้อย 1 คอลัมน์');
        }

        $quotedCols = array_map(fn(string $c) => $this->q($this->validateIdentifier($c)), $columns);
        $namePart = $constraint !== '' ? " CONSTRAINT {$constraint}" : '';

        $this->db->execute(sprintf(
            'ALTER TABLE %s.%s ADD%s PRIMARY KEY (%s)',
            $this->q($schema),
            $this->q($table),
            $namePart,
            implode(', ', $quotedCols)
        ));
    }

    public function addUniqueConstraint(
        string $schema,
        string $table,
        array $columns,
        ?string $name = null
    ): void {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $constraint = $name !== null && $name !== ''
            ? $this->q($this->validateIdentifier($name))
            : '';

        if (empty($columns)) {
            throw new InvalidArgumentException('ต้องระบุคอลัมน์อย่างน้อย 1 คอลัมน์');
        }

        $quotedCols = array_map(fn(string $c) => $this->q($this->validateIdentifier($c)), $columns);
        $namePart = $constraint !== '' ? " CONSTRAINT {$constraint}" : '';

        $this->db->execute(sprintf(
            'ALTER TABLE %s.%s ADD%s UNIQUE (%s)',
            $this->q($schema),
            $this->q($table),
            $namePart,
            implode(', ', $quotedCols)
        ));
    }

    public function addForeignKey(
        string $schema,
        string $table,
        array $columns,
        string $refSchema,
        string $refTable,
        array $refColumns,
        ?string $name = null,
        string $onDelete = 'NO ACTION',
        string $onUpdate = 'NO ACTION'
    ): void {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $refSchema = $this->validateIdentifier($refSchema);
        $refTable = $this->validateIdentifier($refTable);
        $constraint = $name !== null && $name !== ''
            ? $this->q($this->validateIdentifier($name))
            : '';

        if (empty($columns) || empty($refColumns)) {
            throw new InvalidArgumentException('ต้องระบุคอลัมน์อ้างอิงให้ครบ');
        }

        $allowedActions = ['NO ACTION', 'RESTRICT', 'CASCADE', 'SET NULL', 'SET DEFAULT'];
        $onDelete = strtoupper(trim($onDelete));
        $onUpdate = strtoupper(trim($onUpdate));
        if (!in_array($onDelete, $allowedActions, true) || !in_array($onUpdate, $allowedActions, true)) {
            throw new InvalidArgumentException('ON DELETE/UPDATE ไม่ถูกต้อง');
        }

        $quotedCols = array_map(fn(string $c) => $this->q($this->validateIdentifier($c)), $columns);
        $quotedRefCols = array_map(fn(string $c) => $this->q($this->validateIdentifier($c)), $refColumns);
        $namePart = $constraint !== '' ? " CONSTRAINT {$constraint}" : '';

        $this->db->execute(sprintf(
            'ALTER TABLE %s.%s ADD%s FOREIGN KEY (%s) REFERENCES %s.%s (%s) ON DELETE %s ON UPDATE %s',
            $this->q($schema),
            $this->q($table),
            $namePart,
            implode(', ', $quotedCols),
            $this->q($refSchema),
            $this->q($refTable),
            implode(', ', $quotedRefCols),
            $onDelete,
            $onUpdate
        ));
    }

    public function dropConstraint(string $schema, string $table, string $constraintName): void
    {
        $schema = $this->validateIdentifier($schema);
        $table = $this->validateIdentifier($table);
        $constraintName = $this->validateIdentifier($constraintName);

        $this->db->execute(sprintf(
            'ALTER TABLE %s.%s DROP CONSTRAINT %s',
            $this->q($schema),
            $this->q($table),
            $this->q($constraintName)
        ));
    }

    public function importSql(string $sql): array
    {
        $statements = $this->splitSqlStatements($sql);
        $executed = 0;
        $errors = [];

        $this->db->beginTransaction();
        try {
            foreach ($statements as $i => $statement) {
                $statement = trim($statement);
                if ($statement === '' || str_starts_with($statement, '--')) {
                    continue;
                }
                try {
                    $this->db->execute($statement);
                    $executed++;
                } catch (PDOException $e) {
                    $errors[] = "Statement #{$i}: " . $e->getMessage();
                }
            }
            if (!empty($errors)) {
                $this->db->rollBack();
            } else {
                $this->db->commit();
            }
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['executed' => $executed, 'errors' => $errors];
    }

    /* ── Users / Roles ── */

    public function listRoles(): array
    {
        return $this->db->fetchAll("
            SELECT rolname AS name,
                   rolsuper AS is_superuser,
                   rolinherit AS can_inherit,
                   rolcreatedb AS can_create_db,
                   rolcreaterole AS can_create_role,
                   rolcanlogin AS can_login,
                   rolconnlimit AS connection_limit,
                   rolvaliduntil AS valid_until
            FROM pg_catalog.pg_roles
            WHERE rolname NOT LIKE 'pg_%'
            ORDER BY rolname
        ");
    }

    public function createRole(string $name, string $password = '', array $options = []): void
    {
        $name = $this->validateIdentifier($name);
        $sql = "CREATE ROLE {$this->q($name)}";
        if ($password !== '') {
            $sql .= " WITH LOGIN PASSWORD " . $this->db->connect()->quote($password);
        }
        if (!empty($options['superuser'])) {
            $sql .= ' SUPERUSER';
        }
        if (!empty($options['createdb'])) {
            $sql .= ' CREATEDB';
        }
        if (!empty($options['createrole'])) {
            $sql .= ' CREATEROLE';
        }
        $this->db->execute($sql);
    }

    public function dropRole(string $name): void
    {
        $name = $this->validateIdentifier($name);
        $this->db->execute("DROP ROLE {$this->q($name)}");
    }

    /* ── Sequences ── */

    public function listSequences(?string $schema = null): array
    {
        $params = [];
        $where = "sequence_schema NOT IN ('pg_catalog', 'information_schema')";
        if ($schema !== null) {
            $where .= ' AND sequence_schema = :schema';
            $params['schema'] = $schema;
        }

        return $this->db->fetchAll("
            SELECT sequence_schema AS schema,
                   sequence_name AS name,
                   data_type,
                   start_value,
                   minimum_value,
                   maximum_value,
                   increment
            FROM information_schema.sequences
            WHERE {$where}
            ORDER BY sequence_schema, sequence_name
        ", $params);
    }

    /* ── Functions ── */

    public function listFunctions(?string $schema = null): array
    {
        $params = [];
        $where = "n.nspname NOT IN ('pg_catalog', 'information_schema')";
        if ($schema !== null) {
            $where .= ' AND n.nspname = :schema';
            $params['schema'] = $schema;
        }

        return $this->db->fetchAll("
            SELECT n.nspname AS schema,
                   p.proname AS name,
                   pg_catalog.pg_get_function_result(p.oid) AS return_type,
                   pg_catalog.pg_get_function_arguments(p.oid) AS arguments,
                   l.lanname AS language
            FROM pg_catalog.pg_proc p
            JOIN pg_catalog.pg_namespace n ON n.oid = p.pronamespace
            JOIN pg_catalog.pg_language l ON l.oid = p.prolang
            WHERE {$where}
            ORDER BY n.nspname, p.proname
        ", $params);
    }

    /* ── Helpers ── */

    private function getColumnNames(string $schema, string $table): array
    {
        $rows = $this->db->fetchAll("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = :schema AND table_name = :table
            ORDER BY ordinal_position
        ", ['schema' => $schema, 'table' => $table]);

        return array_column($rows, 'column_name');
    }

    private function validateIdentifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("ชื่อไม่ถูกต้อง: {$name}");
        }
        return $name;
    }

    private function q(string $identifier): string
    {
        return $this->db->quoteIdentifier($identifier);
    }

    private function normalizeDefaultExpression(string $default): string
    {
        $default = trim($default);
        if ($default === '') {
            return $default;
        }

        // Keep explicit SQL literals/keywords/expressions untouched.
        if (
            preg_match('/^(NULL|TRUE|FALSE)$/i', $default)
            || is_numeric($default)
            || preg_match('/^\$\$.*\$\$$/s', $default)
            || preg_match('/^(E)?\'.*\'$/s', $default)
            || preg_match('/^CURRENT_(DATE|TIME|TIMESTAMP)$/i', $default)
            || preg_match('/^(LOCALTIME|LOCALTIMESTAMP)$/i', $default)
            || str_contains($default, '::')
            || str_contains($default, '(')
        ) {
            return $default;
        }

        // Common input mistake: using double quotes for string defaults in Postgres.
        if (preg_match('/^"(.*)"$/s', $default, $matches)) {
            return $this->sqlValue(str_replace('""', '"', $matches[1]));
        }

        // Bare words are treated as identifiers by Postgres; quote them as string literals.
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $default)) {
            return $this->sqlValue($default);
        }

        return $default;
    }

    private function sqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        return $this->db->connect()->quote((string) $value);
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];

            if (!$inString && ($char === "'" || $char === '"')) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar) {
                if ($i + 1 < $len && $sql[$i + 1] === $stringChar) {
                    $current .= $char . $char;
                    $i++;
                    continue;
                }
                $inString = false;
            }

            if (!$inString && $char === ';') {
                $statements[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $statements[] = $current;
        }

        return $statements;
    }
}
