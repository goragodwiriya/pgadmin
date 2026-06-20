<?php
$rows = $data['rows'];
$columns = $data['columns'];
$columnMeta = $data['column_meta'] ?? [];
$schema = $data['schema'];
$table = $data['table'];
$total = $data['total'];
$page_num = $data['page'];
$total_pages = $data['total_pages'];
$search = $_GET['search'] ?? '';
?>

<div class="breadcrumb">
    <a href="app.php?page=tables">ตาราง</a> /
    <a href="app.php?page=structure&schema=<?= urlencode($schema) ?>&table=<?= urlencode($table) ?>"><?= e($schema) ?>.<?= e($table) ?></a> /
    <strong>ข้อมูล</strong>
</div>

<div class="toolbar">
    <form method="GET" class="search-form">
        <input type="hidden" name="page" value="data">
        <input type="hidden" name="schema" value="<?= e($schema) ?>">
        <input type="hidden" name="table" value="<?= e($table) ?>">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="ค้นหา...">
        <button type="submit" class="btn btn-outline">ค้นหา</button>
    </form>
    <button class="btn btn-primary" onclick="openModal('insertModal')">+ เพิ่มแถว</button>
    <span class="record-count"><?= number_format($total) ?> แถว</span>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="data-table data-table-editable">
            <thead>
                <tr>
                    <?php foreach ($columns as $col): ?>
                    <th>
                        <a href="app.php?page=data&schema=<?= urlencode($schema) ?>&table=<?= urlencode($table) ?>&order=<?= urlencode($col) ?>&dir=<?= ($_GET['order'] ?? '') === $col && ($_GET['dir'] ?? 'ASC') === 'ASC' ? 'DESC' : 'ASC' ?>&search=<?= urlencode($search) ?>">
                            <?= e($col) ?>
                            <?php if (($_GET['order'] ?? '') === $col): ?>
                                <?= ($_GET['dir'] ?? 'ASC') === 'ASC' ? '▲' : '▼' ?>
                            <?php endif; ?>
                        </a>
                    </th>
                    <?php endforeach; ?>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr><td colspan="<?= count($columns) + 1 ?>" class="empty">ไม่มีข้อมูล</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($columns as $col): ?>
                    <td title="<?= e((string) ($row[$col] ?? '')) ?>">
                        <?php
                        $val = $row[$col] ?? null;
                        if ($val === null) {
                            echo '<span class="null-value">NULL</span>';
                        } elseif (is_bool($val)) {
                            echo $val ? 'true' : 'false';
                        } else {
                            $str = (string) $val;
                            echo e(strlen($str) > 100 ? substr($str, 0, 100) . '...' : $str);
                        }
                        ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="actions">
                        <button class="btn btn-sm btn-outline" onclick='editRow(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>แก้ไข</button>
                        <form method="POST" action="app.php?page=data&action=delete" style="display:inline"
                              onsubmit="return confirm('ยืนยันลบแถวนี้?')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="schema" value="<?= e($schema) ?>">
                            <input type="hidden" name="table" value="<?= e($table) ?>">
                            <?php foreach ($row as $k => $v): ?>
                                <?php if ($v === null): ?>
                                    <input type="hidden" name="where_null[]" value="<?= e($k) ?>">
                                <?php else: ?>
                                    <input type="hidden" name="where[<?= e($k) ?>]" value="<?= e((string) $v) ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <?php if ($p === $page_num): ?>
            <span class="current"><?= $p ?></span>
        <?php else: ?>
            <a href="app.php?page=data&schema=<?= urlencode($schema) ?>&table=<?= urlencode($table) ?>&p=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Insert Modal -->
<div id="insertModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>เพิ่มแถวใหม่</h3>
            <button class="modal-close" onclick="closeModal('insertModal')">&times;</button>
        </div>
        <form method="POST" action="app.php?page=data&action=insert">
            <?php csrfField(); ?>
            <input type="hidden" name="schema" value="<?= e($schema) ?>">
            <input type="hidden" name="table" value="<?= e($table) ?>">
            <div class="modal-body">
                <?php foreach ($columns as $col):
                    $meta = $columnMeta[$col] ?? ['name' => $col, 'input_type' => 'string', 'nullable' => 'YES'];
                    renderColumnField($meta, 'data[' . $col . ']', null);
                endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('insertModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>แก้ไขแถว</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST" action="app.php?page=data&action=update" id="editForm">
            <?php csrfField(); ?>
            <input type="hidden" name="schema" value="<?= e($schema) ?>">
            <input type="hidden" name="table" value="<?= e($table) ?>">
            <div class="modal-body" id="editFields"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
const tableColumns = <?= json_encode($columns, JSON_UNESCAPED_UNICODE) ?>;
const columnMeta = <?= json_encode(array_values($columnMeta), JSON_UNESCAPED_UNICODE) ?>;
const columnMetaMap = <?= json_encode($columnMeta, JSON_UNESCAPED_UNICODE) ?>;
</script>
