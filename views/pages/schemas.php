<?php $schemas = $data['schemas']; ?>

<div class="toolbar">
    <button class="btn btn-primary" onclick="openModal('createSchemaModal')">+ สร้าง Schema</button>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>ชื่อ</th><th>Owner</th><th>จัดการ</th></tr>
            </thead>
            <tbody>
                <?php foreach ($schemas as $s): ?>
                <tr>
                    <td><strong><?= e($s['name']) ?></strong></td>
                    <td><?= e($s['owner']) ?></td>
                    <td class="actions">
                        <a href="app.php?page=tables&schema=<?= urlencode($s['name']) ?>" class="btn btn-sm btn-outline">ดูตาราง</a>
                        <?php if (!in_array($s['name'], ['public', 'pg_catalog', 'information_schema'])): ?>
                        <form method="POST" action="app.php?page=schemas&action=drop" style="display:inline"
                              onsubmit="return confirm('ยืนยันลบ Schema <?= e($s['name']) ?>?')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="name" value="<?= e($s['name']) ?>">
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

<div id="createSchemaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>สร้าง Schema ใหม่</h3>
            <button class="modal-close" onclick="closeModal('createSchemaModal')">&times;</button>
        </div>
        <form method="POST" action="app.php?page=schemas&action=create">
            <?php csrfField(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>ชื่อ Schema</label>
                    <input type="text" name="name" required pattern="[a-zA-Z_][a-zA-Z0-9_]*">
                </div>
                <div class="form-group">
                    <label>Owner (ไม่บังคับ)</label>
                    <input type="text" name="owner">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createSchemaModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">สร้าง</button>
            </div>
        </form>
    </div>
</div>
