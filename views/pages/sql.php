<?php
$lastSql = $data['last_sql'];
$result = $data['result'];
$history = $data['history'] ?? [];
?>

<div class="sql-layout">
    <?php if (!empty($history)): ?>
    <aside class="sql-history">
        <h4><?= e(__('sql.history')) ?></h4>
        <ul>
            <?php foreach ($history as $item): ?>
            <li>
                <button type="button" class="history-item" data-sql="<?= e($item) ?>" title="<?= e($item) ?>">
                    <?= e(mb_strlen($item) > 80 ? mb_substr($item, 0, 80) . '…' : $item) ?>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
    </aside>
    <?php endif; ?>

    <div class="sql-main">
        <div class="card">
            <div class="card-header"><h3>SQL Query Editor</h3></div>
            <div class="card-body">
                <form method="POST" action="app.php?page=sql">
                    <?php csrfField(); ?>
                    <textarea name="sql" class="sql-editor" rows="10" placeholder="SELECT * FROM ..."><?= e($lastSql) ?></textarea>
                    <div class="toolbar" style="margin-top:12px">
                        <button type="submit" class="btn btn-primary">▶ <?= e(__('sql.run')) ?></button>
                        <button type="button" class="btn btn-outline" onclick="document.querySelector('.sql-editor').value=''">ล้าง</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($result !== null): ?>
        <div class="card">
            <div class="card-header">
                <h3>ผลลัพธ์</h3>
                <span class="badge"><?= e((string) $result['elapsed_ms']) ?> ms</span>
                <?php if ($result['type'] === 'select'): ?>
                    <span class="badge"><?= e((string) $result['row_count']) ?> แถว</span>
                    <?php if (!empty($result['truncated'])): ?>
                        <span class="badge badge-warning">แสดงสูงสุด <?= config('max_query_rows') ?> แถว</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge"><?= e((string) $result['affected_rows']) ?> แถวที่ได้รับผล</span>
                <?php endif; ?>
            </div>
            <div class="card-body table-responsive">
                <?php if ($result['type'] === 'select' && !empty($result['rows'])): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php foreach ($result['columns'] as $col): ?>
                            <th><?= e($col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <?php foreach ($result['columns'] as $col): ?>
                            <td><?php
                                $val = $row[$col] ?? null;
                                echo $val === null ? '<span class="null-value">NULL</span>' : e((string) $val);
                            ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php elseif ($result['type'] === 'select'): ?>
                    <p class="empty">ไม่มีผลลัพธ์</p>
                <?php else: ?>
                    <p class="success-msg">✓ คำสั่งดำเนินการสำเร็จ</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h3>คำสั่งที่ใช้บ่อย</h3></div>
            <div class="card-body">
                <div class="sql-snippets">
                    <?php
                    $snippets = [
                        'SELECT * FROM table_name LIMIT 100;' => 'SELECT ข้อมูล',
                        "SELECT tablename FROM pg_tables WHERE schemaname = 'public';" => 'รายชื่อตาราง',
                        "SELECT * FROM pg_stat_activity;" => 'Active Connections',
                        "SELECT pg_size_pretty(pg_database_size(current_database()));" => 'ขนาด DB',
                    ];
                    foreach ($snippets as $sql => $label):
                    ?>
                    <button class="btn btn-sm btn-outline snippet-btn" data-sql="<?= e($sql) ?>"><?= e($label) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
