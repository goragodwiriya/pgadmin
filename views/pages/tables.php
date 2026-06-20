<?php
$tables = $data['tables'];
$schemas = $data['schemas'];
$currentSchema = $data['current_schema'];
?>

<div class="toolbar">
    <form method="GET" class="filter-form">
        <input type="hidden" name="page" value="tables">
        <select name="schema" onchange="this.form.submit()">
            <option value="">ทุก Schema</option>
            <?php foreach ($schemas as $s): ?>
            <option value="<?= e($s['name']) ?>" <?= $currentSchema === $s['name'] ? 'selected' : '' ?>>
                <?= e($s['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if (!isReadOnly()): ?>
    <form method="POST" action="app.php?page=tables&action=analyze" style="display:inline">
        <?php csrfField(); ?>
        <button type="submit" class="btn btn-outline" title="รันคำสั่ง ANALYZE เพื่ออัปเดตข้อมูลสถิติของทุกตาราง">วิเคราะห์ฐานข้อมูล (Analyze)</button>
    </form>
    <button class="btn btn-primary" onclick="openModal('createTableModal')">+ สร้างตาราง</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Schema</th>
                    <th>ชื่อตาราง</th>
                    <th>ขนาด</th>
                    <th>แถว (ประมาณ)</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tables)): ?>
                <tr><td colspan="5" class="empty">ไม่พบตาราง</td></tr>
                <?php endif; ?>
                <?php foreach ($tables as $t): ?>
                <tr>
                    <td><code><?= e($t['schema']) ?></code></td>
                    <td><strong><?= e($t['name']) ?></strong></td>
                    <td><?= e($t['size']) ?></td>
                    <td><?= e((string) ($t['estimated_rows'] ?? 0)) ?></td>
                    <td class="actions">
                        <a href="app.php?page=data&schema=<?= urlencode($t['schema']) ?>&table=<?= urlencode($t['name']) ?>" class="btn btn-sm btn-primary">ข้อมูล</a>
                        <a href="app.php?page=structure&schema=<?= urlencode($t['schema']) ?>&table=<?= urlencode($t['name']) ?>" class="btn btn-sm btn-outline">โครงสร้าง</a>
                        <a href="app.php?page=export&schema=<?= urlencode($t['schema']) ?>&table=<?= urlencode($t['name']) ?>&format=sql" class="btn btn-sm btn-outline">SQL</a>
                        <a href="app.php?page=export&schema=<?= urlencode($t['schema']) ?>&table=<?= urlencode($t['name']) ?>&format=csv" class="btn btn-sm btn-outline">CSV</a>
                        <a href="app.php?page=export&schema=<?= urlencode($t['schema']) ?>&table=<?= urlencode($t['name']) ?>&format=json" class="btn btn-sm btn-outline">JSON</a>
                        <?php if (!isReadOnly()): ?>
                        <form method="POST" action="app.php?page=tables&action=analyze" style="display:inline">
                            <?php csrfField(); ?>
                            <input type="hidden" name="schema" value="<?= e($t['schema']) ?>">
                            <input type="hidden" name="name" value="<?= e($t['name']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline" title="รันคำสั่ง ANALYZE บนตารางนี้เพื่ออัปเดตข้อมูลสถิติจำนวนแถว">Analyze</button>
                        </form>
                        <form method="POST" action="app.php?page=tables&action=truncate" style="display:inline"
                              onsubmit="return confirm('ล้างข้อมูลทั้งหมดในตาราง <?= e($t['name']) ?>?')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="schema" value="<?= e($t['schema']) ?>">
                            <input type="hidden" name="name" value="<?= e($t['name']) ?>">
                            <button type="submit" class="btn btn-sm btn-warning">Truncate</button>
                        </form>
                        <form method="POST" action="app.php?page=tables&action=drop" style="display:inline"
                              onsubmit="return confirm('ยืนยันลบตาราง <?= e($t['name']) ?>?')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="schema" value="<?= e($t['schema']) ?>">
                            <input type="hidden" name="name" value="<?= e($t['name']) ?>">
                            <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="createTableModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>สร้างตารางใหม่</h3>
            <button class="modal-close" onclick="closeModal('createTableModal')">&times;</button>
        </div>
        <form method="POST" action="app.php?page=tables&action=create" id="createTableForm">
            <?php csrfField(); ?>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Schema</label>
                        <input type="text" name="schema" value="public" required>
                    </div>
                    <div class="form-group">
                        <label>ชื่อตาราง</label>
                        <input type="text" name="name" required pattern="[a-zA-Z_][a-zA-Z0-9_]*">
                    </div>
                </div>
                <h4>คอลัมน์</h4>
                <div id="columnsContainer">
                    <div class="column-row">
                        <input type="text" placeholder="ชื่อคอลัมน์" class="col-name" value="id">
                        <select class="col-type">
                            <option value="SERIAL">SERIAL</option>
                            <option value="INTEGER">INTEGER</option>
                            <option value="BIGINT">BIGINT</option>
                            <option value="VARCHAR(255)">VARCHAR(255)</option>
                            <option value="TEXT">TEXT</option>
                            <option value="BOOLEAN">BOOLEAN</option>
                            <option value="DATE">DATE</option>
                            <option value="TIMESTAMP">TIMESTAMP</option>
                            <option value="NUMERIC(10,2)">NUMERIC(10,2)</option>
                            <option value="JSONB">JSONB</option>
                        </select>
                        <label><input type="checkbox" class="col-pk" checked> PK</label>
                        <label><input type="checkbox" class="col-nn"> NOT NULL</label>
                        <input type="text" placeholder="Default" class="col-default">
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline" onclick="addColumnRow()">+ เพิ่มคอลัมน์</button>
                <input type="hidden" name="columns_json" id="columnsJson">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createTableModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary" onclick="return prepareColumns()">สร้าง</button>
            </div>
        </form>
    </div>
</div>
