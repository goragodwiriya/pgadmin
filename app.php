<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? '';

if ($page === 'databases' && $action === 'go') {
    $dbName = trim($_GET['db'] ?? '');
    $to = trim($_GET['to'] ?? 'tables');
    $allowed = array_merge(dbRequiredPages(), ['dashboard']);
    if ($dbName !== '' && in_array($to, $allowed, true)) {
        Session::setCurrentDatabase($dbName);
        redirect('app.php?page=' . rawurlencode($to));
    }
    redirect('app.php?page=databases');
}

$db = requireConnection();
$pg = new PgManager($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail('app.php?page=' . rawurlencode($page));
    handlePost($pg, $page, $action);
}

function handlePost(PgManager $pg, string $page, string $action): void
{
    $writeActions = [
        'databases' => ['create', 'drop', 'switch'],
        'schemas' => ['create', 'drop'],
        'tables' => ['create', 'drop', 'truncate', 'analyze'],
        'data' => ['insert', 'update', 'delete'],
        'import' => [''],
        'structure' => ['add_column', 'drop_column', 'alter_column', 'add_index', 'drop_index', 'add_constraint', 'drop_constraint'],
        'roles' => ['create', 'drop'],
    ];

    $needsWrite = in_array($action, $writeActions[$page] ?? [], true)
        || ($page === 'import')
        || ($page === 'sql' && !isReadOnlySql($_POST['sql'] ?? ''));

    if ($needsWrite) {
        requireWritable('app.php?page=' . rawurlencode($page));
    }

    try {
        switch ($page) {
            case 'databases':
                if ($action === 'create') {
                    $pg->createDatabase(
                        trim($_POST['name'] ?? ''),
                        trim($_POST['owner'] ?? ''),
                        trim($_POST['encoding'] ?? 'UTF8')
                    );
                    flash('success', 'สร้างฐานข้อมูลสำเร็จ');
                } elseif ($action === 'drop') {
                    $pg->dropDatabase(trim($_POST['name'] ?? ''));
                    flash('success', 'ลบฐานข้อมูลสำเร็จ');
                } elseif ($action === 'switch') {
                    $dbName = trim($_POST['name'] ?? '');
                    Session::setCurrentDatabase($dbName);
                    flash('success', "เปลี่ยนไปใช้ฐานข้อมูล {$dbName}");
                }
                break;

            case 'schemas':
                if ($action === 'create') {
                    $pg->createSchema(trim($_POST['name'] ?? ''), trim($_POST['owner'] ?? ''));
                    flash('success', 'สร้าง Schema สำเร็จ');
                } elseif ($action === 'drop') {
                    $pg->dropSchema(trim($_POST['name'] ?? ''), !empty($_POST['cascade']));
                    flash('success', 'ลบ Schema สำเร็จ');
                }
                break;

            case 'tables':
                if ($action === 'create') {
                    $columns = json_decode($_POST['columns_json'] ?? '[]', true) ?: [];
                    $pg->createTable(
                        trim($_POST['schema'] ?? 'public'),
                        trim($_POST['name'] ?? ''),
                        $columns
                    );
                    flash('success', 'สร้างตารางสำเร็จ');
                } elseif ($action === 'drop') {
                    $pg->dropTable(
                        trim($_POST['schema'] ?? ''),
                        trim($_POST['name'] ?? ''),
                        !empty($_POST['cascade'])
                    );
                    flash('success', 'ลบตารางสำเร็จ');
                } elseif ($action === 'truncate') {
                    $pg->truncateTable(trim($_POST['schema'] ?? ''), trim($_POST['name'] ?? ''));
                    flash('success', 'ล้างข้อมูลตารางสำเร็จ');
                } elseif ($action === 'analyze') {
                    $schema = trim($_POST['schema'] ?? '');
                    $name = trim($_POST['name'] ?? '');
                    if ($schema !== '' && $name !== '') {
                        $pg->analyzeTable($schema, $name);
                        flash('success', "วิเคราะห์และอัปเดตสถิติตาราง {$schema}.{$name} สำเร็จ");
                    } else {
                        $pg->analyzeDatabase();
                        flash('success', 'วิเคราะห์และอัปเดตสถิติฐานข้อมูลสำเร็จ');
                    }
                }
                break;

            case 'data':
                $schema = trim($_POST['schema'] ?? '');
                $table = trim($_POST['table'] ?? '');
                if ($action === 'insert') {
                    $data = array_filter($_POST['data'] ?? [], fn($v) => $v !== '');
                    $pg->insertRow($schema, $table, $data);
                    flash('success', 'เพิ่มข้อมูลสำเร็จ');
                } elseif ($action === 'update') {
                    $data = $_POST['data'] ?? [];
                    $where = $_POST['where'] ?? [];
                    $whereNull = $_POST['where_null'] ?? [];
                    $pg->updateRow($schema, $table, $data, $where, $whereNull);
                    flash('success', 'แก้ไขข้อมูลสำเร็จ');
                } elseif ($action === 'delete') {
                    $where = $_POST['where'] ?? [];
                    $whereNull = $_POST['where_null'] ?? [];
                    $pg->deleteRow($schema, $table, $where, $whereNull);
                    flash('success', 'ลบข้อมูลสำเร็จ');
                }
                break;

            case 'sql':
                $sql = $_POST['sql'] ?? '';
                $_SESSION['last_sql'] = $sql;
                pushQueryHistory($sql);
                $result = $pg->executeQuery($sql, config('max_query_rows'));
                $_SESSION['sql_result'] = $result;
                if ($result['type'] === 'command') {
                    flash('success', "ดำเนินการสำเร็จ ({$result['affected_rows']} แถว, {$result['elapsed_ms']} ms)");
                } else {
                    flash('success', "ได้ผลลัพธ์ {$result['row_count']} แถว ({$result['elapsed_ms']} ms)");
                }
                break;

            case 'import':
                $sql = file_get_contents($_FILES['sql_file']['tmp_name'] ?? '') ?: ($_POST['sql_text'] ?? '');
                $result = $pg->importSql($sql);
                if (!empty($result['errors'])) {
                    flash('error', implode('<br>', $result['errors']));
                } else {
                    flash('success', "Import สำเร็จ {$result['executed']} คำสั่ง");
                }
                break;

            case 'structure':
                $schema = trim($_POST['schema'] ?? '');
                $table = trim($_POST['table'] ?? '');
                if ($action === 'add_column') {
                    $pg->addColumn(
                        $schema,
                        $table,
                        trim($_POST['column_name'] ?? ''),
                        trim($_POST['column_type'] ?? 'TEXT'),
                        !empty($_POST['not_null']),
                        trim($_POST['default_value'] ?? '') ?: null
                    );
                    flash('success', 'เพิ่มคอลัมน์สำเร็จ');
                } elseif ($action === 'drop_column') {
                    $pg->dropColumn($schema, $table, trim($_POST['column_name'] ?? ''));
                    flash('success', 'ลบคอลัมน์สำเร็จ');
                } elseif ($action === 'alter_column') {
                    $changes = [];
                    $newName = trim($_POST['new_name'] ?? '');
                    $oldName = trim($_POST['column_name'] ?? '');
                    if ($newName !== '' && $newName !== $oldName) {
                        $changes['new_name'] = $newName;
                    }
                    $colType = trim($_POST['column_type'] ?? '');
                    if ($colType !== '') {
                        $changes['type'] = $colType;
                    }
                    if (!empty($_POST['drop_default'])) {
                        $changes['drop_default'] = true;
                    } elseif (trim($_POST['default_value'] ?? '') !== '') {
                        $changes['default'] = trim($_POST['default_value']);
                    }
                    if (!empty($_POST['not_null_set'])) {
                        $changes['not_null'] = !empty($_POST['not_null']);
                    }
                    $pg->alterColumn($schema, $table, $oldName, $changes);
                    flash('success', 'แก้ไขคอลัมน์สำเร็จ');
                } elseif ($action === 'add_index') {
                    $columns = array_filter(array_map('trim', explode(',', $_POST['columns'] ?? '')));
                    $pg->addIndex(
                        $schema,
                        $table,
                        trim($_POST['index_name'] ?? ''),
                        $columns,
                        !empty($_POST['unique'])
                    );
                    flash('success', 'เพิ่ม Index สำเร็จ');
                } elseif ($action === 'drop_index') {
                    $pg->dropIndex($schema, trim($_POST['index_name'] ?? ''));
                    flash('success', 'ลบ Index สำเร็จ');
                } elseif ($action === 'add_constraint') {
                    $type = strtoupper(trim($_POST['constraint_type'] ?? ''));
                    $columns = array_filter(array_map('trim', explode(',', $_POST['columns'] ?? '')));
                    $name = trim($_POST['constraint_name'] ?? '') ?: null;
                    if ($type === 'PRIMARY KEY') {
                        $pg->addPrimaryKey($schema, $table, $columns, $name);
                    } elseif ($type === 'UNIQUE') {
                        $pg->addUniqueConstraint($schema, $table, $columns, $name);
                    } elseif ($type === 'FOREIGN KEY') {
                        $refColumns = array_filter(array_map('trim', explode(',', $_POST['ref_columns'] ?? '')));
                        $pg->addForeignKey(
                            $schema,
                            $table,
                            $columns,
                            trim($_POST['ref_schema'] ?? 'public'),
                            trim($_POST['ref_table'] ?? ''),
                            $refColumns,
                            $name,
                            trim($_POST['on_delete'] ?? 'NO ACTION'),
                            trim($_POST['on_update'] ?? 'NO ACTION')
                        );
                    } else {
                        throw new InvalidArgumentException('ประเภท Constraint ไม่รองรับ');
                    }
                    flash('success', 'เพิ่ม Constraint สำเร็จ');
                } elseif ($action === 'drop_constraint') {
                    $pg->dropConstraint($schema, $table, trim($_POST['constraint_name'] ?? ''));
                    flash('success', 'ลบ Constraint สำเร็จ');
                }
                break;

            case 'roles':
                if ($action === 'create') {
                    $pg->createRole(
                        trim($_POST['name'] ?? ''),
                        $_POST['password'] ?? '',
                        [
                            'superuser' => !empty($_POST['superuser']),
                            'createdb' => !empty($_POST['createdb']),
                            'createrole' => !empty($_POST['createrole']),
                        ]
                    );
                    flash('success', 'สร้าง Role สำเร็จ');
                } elseif ($action === 'drop') {
                    $pg->dropRole(trim($_POST['name'] ?? ''));
                    flash('success', 'ลบ Role สำเร็จ');
                }
                break;
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    $redirectPage = $page === 'structure' ? 'structure' : $page;
    $redirect = 'app.php?page=' . urlencode($redirectPage);
    foreach (['schema', 'table'] as $param) {
        $val = $_POST[$param] ?? $_GET[$param] ?? '';
        if ($val !== '') {
            $redirect .= '&' . $param . '=' . urlencode((string) $val);
        }
    }
    redirect($redirect);
}

function pushQueryHistory(string $sql): void
{
    $sql = trim($sql);
    if ($sql === '') {
        return;
    }

    $history = $_SESSION['query_history'] ?? [];
    $history = array_values(array_filter($history, static fn(string $item): bool => $item !== $sql));
    array_unshift($history, $sql);

    $limit = (int) config('query_history_limit', 20);
    $_SESSION['query_history'] = array_slice($history, 0, max(1, $limit));
}

if ($page === 'logout') {
    Session::destroy();
    redirect('index.php');
}

if ($page === 'export') {
    $schema = $_GET['schema'] ?? '';
    $table = $_GET['table'] ?? '';
    $format = $_GET['format'] ?? 'sql';
    $exportAction = $_GET['action'] ?? 'table';

    $allowedFormats = config('allowed_export_formats', ['sql', 'csv', 'json']);

    try {
        if ($exportAction === 'database') {
            $dbName = Session::getCurrentDatabase();
            header('Content-Type: application/sql; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"{$dbName}_backup.sql\"");
            echo $pg->exportDatabaseSql($dbName);
        } elseif ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"{$schema}_{$table}.csv\"");
            echo $pg->exportTableCsv($schema, $table);
        } elseif ($format === 'json' && in_array('json', $allowedFormats, true)) {
            header('Content-Type: application/json; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"{$schema}_{$table}.json\"");
            echo $pg->exportTableJson($schema, $table);
        } else {
            header('Content-Type: application/sql; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"{$schema}_{$table}.sql\"");
            echo $pg->exportTableSql($schema, $table);
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('app.php?page=tables');
    }
    exit;
}

$db = Session::getConnection();
$pg = new PgManager($db);

$data = match ($page) {
    'dashboard' => ['info' => $pg->getServerInfo()],
    'databases' => ['databases' => $pg->listDatabases()],
    'schemas' => ['schemas' => $pg->listSchemas()],
    'tables' => [
        'tables' => $pg->listTables($_GET['schema'] ?? null),
        'schemas' => $pg->listSchemas(),
        'current_schema' => $_GET['schema'] ?? null,
    ],
    'views' => [
        'views' => $pg->listViews($_GET['schema'] ?? null),
        'schemas' => $pg->listSchemas(),
    ],
    'structure' => [
        'structure' => $pg->getTableStructure($_GET['schema'] ?? 'public', $_GET['table'] ?? ''),
        'schema' => $_GET['schema'] ?? 'public',
        'table' => $_GET['table'] ?? '',
    ],
    'data' => array_merge(
        $pg->getTableData(
            $_GET['schema'] ?? 'public',
            $_GET['table'] ?? '',
            max(1, (int) ($_GET['p'] ?? 1)),
            config('rows_per_page'),
            $_GET['order'] ?? null,
            $_GET['dir'] ?? 'ASC',
            $_GET['search'] ?? null,
            $_GET['search_col'] ?? null
        ),
        [
            'schema' => $_GET['schema'] ?? 'public',
            'table' => $_GET['table'] ?? '',
            'column_meta' => $pg->getTableColumnMeta(
                $_GET['schema'] ?? 'public',
                $_GET['table'] ?? ''
            ),
        ]
    ),
    'sql' => [
        'last_sql' => $_SESSION['last_sql'] ?? '',
        'result' => $_SESSION['sql_result'] ?? null,
        'history' => $_SESSION['query_history'] ?? [],
    ],
    'import' => [],
    'backup' => ['database' => Session::getCurrentDatabase()],
    'roles' => ['roles' => $pg->listRoles()],
    'sequences' => ['sequences' => $pg->listSequences($_GET['schema'] ?? null)],
    'functions' => ['functions' => $pg->listFunctions($_GET['schema'] ?? null)],
    default => ['info' => $pg->getServerInfo()],
};

$pageTitle = match ($page) {
    'dashboard' => 'แดชบอร์ด',
    'databases' => 'ฐานข้อมูล',
    'schemas' => 'Schemas',
    'tables' => 'ตาราง',
    'views' => 'Views',
    'structure' => 'โครงสร้างตาราง',
    'data' => 'ข้อมูล',
    'sql' => 'SQL Query',
    'import' => 'Import',
    'backup' => __('nav.backup'),
    'roles' => 'Roles / Users',
    'sequences' => 'Sequences',
    'functions' => 'Functions',
    default => 'แดชบอร์ด',
};

$credentials = Session::getCredentials();
require __DIR__ . '/views/layout.php';
