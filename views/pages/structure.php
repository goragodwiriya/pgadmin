<?php
$structure = $data['structure'];
$schema = $data['schema'];
$table = $data['table'];

$typeOptions = [
    'SERIAL', 'INTEGER', 'BIGINT', 'SMALLINT',
    'VARCHAR(255)', 'TEXT', 'CHAR(10)',
    'BOOLEAN', 'DATE', 'TIME', 'TIMESTAMP', 'TIMESTAMPTZ',
    'NUMERIC(10,2)', 'REAL', 'DOUBLE PRECISION',
    'JSON', 'JSONB', 'UUID',
];
?>

<div class="breadcrumb">
    <a href="app.php?page=tables">ตาราง</a> /
    <strong><?= e($schema) ?>.<?= e($table) ?></strong>
</div>

<div class="toolbar">
    <a href="app.php?page=data&schema=<?= urlencode($schema) ?>&table=<?= urlencode($table) ?>" class="btn btn-primary">ดูข้อมูล</a>
    <?php if (!isReadOnly()): ?>
    <button class="btn btn-primary" onclick="openModal('addColumnModal')">+ เพิ่มคอลัมน์</button>
    <?php endif; ?>
    <a href="app.php?page=export&schema=<?= urlencode($schema) ?>&table=<?= urlencode($table) ?>&format=sql" class="btn btn-outline"><?= e(__('export.sql')) ?></a>
    <a href="app.php?page=export&schema=<?= urlencode($schema) ?>&table=<?= urlencode($table) ?>&format=csv" class="btn btn-outline"><?= e(__('export.csv')) ?></a>
    <a href="app.php?page=export&schema=<?= urlencode($schema) ?>&table=<?= urlencode($table) ?>&format=json" class="btn btn-outline"><?= e(__('export.json')) ?></a>
</div>

<div class="card">
    <div class="card-header"><h3>คอลัมน์</h3></div>
    <div class="card-body table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ชื่อ</th>
                    <th>ชนิดข้อมูล</th>
                    <th>Nullable</th>
                    <th>Default</th>
                    <th>PK</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($structure['columns'] as $col): ?>
                <tr>
                    <td><strong><?= e($col['name']) ?></strong></td>
                    <td><code><?= e($col['full_type'] ?? $col['type']) ?></code></td>
                    <td><?= $col['nullable'] === 'YES' ? '✓' : '✗' ?></td>
                    <td><code><?= e($col['default_value'] ?? '') ?></code></td>
                    <td><?= $col['is_primary_key'] ? '🔑' : '' ?></td>
                    <td class="actions">
                        <?php if (!isReadOnly()): ?>
                        <button type="button" class="btn btn-sm btn-outline"
                                onclick='openEditColumn(<?= json_encode($col, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>
                            แก้ไข
                        </button>
                        <?php if (!$col['is_primary_key']): ?>
                        <form method="POST" action="app.php?page=structure&action=drop_column" style="display:inline"
                              onsubmit="return confirm('ยืนยันลบคอลัมน์ <?= e($col['name']) ?>?')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="schema" value="<?= e($schema) ?>">
                            <input type="hidden" name="table" value="<?= e($table) ?>">
                            <input type="hidden" name="column_name" value="<?= e($col['name']) ?>">
                            <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($structure['indexes'])): ?>
<div class="card">
    <div class="card-header">
        <h3><?= e(__('structure.indexes')) ?></h3>
        <?php if (!isReadOnly()): ?>
        <button class="btn btn-sm btn-primary" onclick="openModal('addIndexModal')">+ <?= e(__('structure.add_index')) ?></button>
        <?php endif; ?>
    </div>
    <div class="card-body table-responsive">
        <table class="data-table">
            <thead><tr><th>ชื่อ</th><th>Definition</th><?php if (!isReadOnly()): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
                <?php foreach ($structure['indexes'] as $idx): ?>
                <tr>
                    <td><?= e($idx['name']) ?></td>
                    <td><code><?= e($idx['definition']) ?></code></td>
                    <?php if (!isReadOnly()): ?>
                    <td>
                        <form method="POST" action="app.php?page=structure&action=drop_index" style="display:inline"
                              onsubmit="return confirm('ลบ index <?= e($idx['name']) ?>?')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="schema" value="<?= e($schema) ?>">
                            <input type="hidden" name="table" value="<?= e($table) ?>">
                            <input type="hidden" name="index_name" value="<?= e($idx['name']) ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><?= e(__('structure.drop')) ?></button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif (!isReadOnly()): ?>
<div class="card">
    <div class="card-header">
        <h3><?= e(__('structure.indexes')) ?></h3>
        <button class="btn btn-sm btn-primary" onclick="openModal('addIndexModal')">+ <?= e(__('structure.add_index')) ?></button>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($structure['constraints'])): ?>
<div class="card">
    <div class="card-header">
        <h3><?= e(__('structure.constraints')) ?></h3>
        <?php if (!isReadOnly()): ?>
        <button class="btn btn-sm btn-primary" onclick="openModal('addConstraintModal')">+ <?= e(__('structure.add_constraint')) ?></button>
        <?php endif; ?>
    </div>
    <div class="card-body table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>ชื่อ</th><th>ประเภท</th><th>คอลัมน์</th><th>อ้างอิง</th><?php if (!isReadOnly()): ?><th></th><?php endif; ?></tr>
            </thead>
            <tbody>
                <?php foreach ($structure['constraints'] as $c): ?>
                <tr>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['type']) ?></td>
                    <td><?= e($c['column_name'] ?? '') ?></td>
                    <td><?php if ($c['foreign_table']): ?><?= e($c['foreign_schema'] . '.' . $c['foreign_table'] . '.' . $c['foreign_column']) ?><?php endif; ?></td>
                    <?php if (!isReadOnly()): ?>
                    <td>
                        <form method="POST" action="app.php?page=structure&action=drop_constraint" style="display:inline"
                              onsubmit="return confirm('ลบ constraint <?= e($c['name']) ?>?')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="schema" value="<?= e($schema) ?>">
                            <input type="hidden" name="table" value="<?= e($table) ?>">
                            <input type="hidden" name="constraint_name" value="<?= e($c['name']) ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><?= e(__('structure.drop')) ?></button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif (!isReadOnly()): ?>
<div class="card">
    <div class="card-header">
        <h3><?= e(__('structure.constraints')) ?></h3>
        <button class="btn btn-sm btn-primary" onclick="openModal('addConstraintModal')">+ <?= e(__('structure.add_constraint')) ?></button>
    </div>
</div>
<?php endif; ?>

<!-- Add Index Modal -->
<div id="addIndexModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?= e(__('structure.add_index')) ?></h3>
            <button class="modal-close" onclick="closeModal('addIndexModal')">&times;</button>
        </div>
        <form method="POST" action="app.php?page=structure&action=add_index">
            <?php csrfField(); ?>
            <input type="hidden" name="schema" value="<?= e($schema) ?>">
            <input type="hidden" name="table" value="<?= e($table) ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>ชื่อ Index</label>
                    <input type="text" name="index_name" required pattern="[a-zA-Z_][a-zA-Z0-9_]*">
                </div>
                <div class="form-group">
                    <label>คอลัมน์ (คั่นด้วย comma)</label>
                    <input type="text" name="columns" required placeholder="เช่น email หรือ last_name, first_name">
                </div>
                <label class="checkbox-label"><input type="checkbox" name="unique"> UNIQUE</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addIndexModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">เพิ่ม</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Constraint Modal -->
<div id="addConstraintModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><?= e(__('structure.add_constraint')) ?></h3>
            <button class="modal-close" onclick="closeModal('addConstraintModal')">&times;</button>
        </div>
        <form method="POST" action="app.php?page=structure&action=add_constraint">
            <?php csrfField(); ?>
            <input type="hidden" name="schema" value="<?= e($schema) ?>">
            <input type="hidden" name="table" value="<?= e($table) ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>ประเภท</label>
                        <select name="constraint_type" id="constraintType" onchange="toggleFkFields()">
                            <option value="PRIMARY KEY">PRIMARY KEY</option>
                            <option value="UNIQUE">UNIQUE</option>
                            <option value="FOREIGN KEY">FOREIGN KEY</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ชื่อ (ไม่บังคับ)</label>
                        <input type="text" name="constraint_name" pattern="[a-zA-Z_][a-zA-Z0-9_]*">
                    </div>
                </div>
                <div class="form-group">
                    <label>คอลัมน์ (คั่นด้วย comma)</label>
                    <input type="text" name="columns" required>
                </div>
                <div id="fkFields" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ref Schema</label>
                            <input type="text" name="ref_schema" value="<?= e($schema) ?>">
                        </div>
                        <div class="form-group">
                            <label>Ref Table</label>
                            <input type="text" name="ref_table">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Ref Columns (comma)</label>
                        <input type="text" name="ref_columns">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ON DELETE</label>
                            <select name="on_delete">
                                <option>NO ACTION</option>
                                <option>CASCADE</option>
                                <option>SET NULL</option>
                                <option>RESTRICT</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>ON UPDATE</label>
                            <select name="on_update">
                                <option>NO ACTION</option>
                                <option>CASCADE</option>
                                <option>SET NULL</option>
                                <option>RESTRICT</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addConstraintModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">เพิ่ม</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Column Modal -->
<div id="addColumnModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>เพิ่มคอลัมน์</h3>
            <button class="modal-close" onclick="closeModal('addColumnModal')">&times;</button>
        </div>
        <form method="POST" action="app.php?page=structure&action=add_column">
            <?php csrfField(); ?>
            <input type="hidden" name="schema" value="<?= e($schema) ?>">
            <input type="hidden" name="table" value="<?= e($table) ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>ชื่อคอลัมน์</label>
                    <input type="text" name="column_name" required pattern="[a-zA-Z_][a-zA-Z0-9_]*">
                </div>
                <div class="form-group">
                    <label>ชนิดข้อมูล</label>
                    <select name="column_type">
                        <?php foreach ($typeOptions as $t): ?>
                        <option value="<?= e($t) ?>"><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Default (ไม่บังคับ)</label>
                    <input type="text" name="default_value" placeholder="เช่น 0, 'text', now()">
                </div>
                <label class="checkbox-label"><input type="checkbox" name="not_null"> NOT NULL</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addColumnModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">เพิ่ม</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Column Modal -->
<div id="editColumnModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>แก้ไขคอลัมน์</h3>
            <button class="modal-close" onclick="closeModal('editColumnModal')">&times;</button>
        </div>
        <form method="POST" action="app.php?page=structure&action=alter_column">
            <?php csrfField(); ?>
            <input type="hidden" name="schema" value="<?= e($schema) ?>">
            <input type="hidden" name="table" value="<?= e($table) ?>">
            <input type="hidden" name="column_name" id="editColName">
            <div class="modal-body">
                <div class="form-group">
                    <label>ชื่อใหม่ (ไม่บังคับ)</label>
                    <input type="text" name="new_name" id="editColNewName" pattern="[a-zA-Z_][a-zA-Z0-9_]*">
                </div>
                <div class="form-group">
                    <label>ชนิดข้อมูล</label>
                    <select name="column_type" id="editColType">
                        <option value="">— ไม่เปลี่ยน —</option>
                        <?php foreach ($typeOptions as $t): ?>
                        <option value="<?= e($t) ?>"><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Default ใหม่</label>
                    <input type="text" name="default_value" id="editColDefault" placeholder="เช่น 'text', now() · เว้นว่าง = ไม่เปลี่ยน">
                </div>
                <div class="form-checks">
                    <input type="hidden" name="not_null_set" value="1">
                    <label><input type="checkbox" name="not_null" id="editColNotNull" value="1"> NOT NULL</label>
                    <label><input type="checkbox" name="drop_default" id="editColDropDefault" value="1"> ลบ Default</label>
                </div>
                <p class="form-hint">NOT NULL: ติ๊ก = บังคับ, ไม่ติ๊ก = อนุญาต NULL · Default ว่าง = ไม่เปลี่ยน</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editColumnModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleFkFields() {
    const type = document.getElementById('constraintType')?.value;
    const fk = document.getElementById('fkFields');
    if (fk) fk.style.display = type === 'FOREIGN KEY' ? 'block' : 'none';
}

function openEditColumn(col) {
    document.getElementById('editColName').value = col.name;
    document.getElementById('editColNewName').value = col.name;
    document.getElementById('editColType').value = '';
    document.getElementById('editColDefault').value = '';
    document.getElementById('editColNotNull').checked = col.nullable === 'NO';
    document.getElementById('editColDropDefault').checked = false;
    openModal('editColumnModal');
}
</script>
