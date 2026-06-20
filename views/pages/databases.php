<?php
$databases = $data['databases'];
$currentDb = Session::getCurrentDatabase();
$dbTools = [
    'schemas' => ['label' => 'Schemas', 'icon' => '📁'],
    'tables' => ['label' => 'Tables', 'icon' => '📋'],
    'views' => ['label' => 'Views', 'icon' => '👁️'],
    'sequences' => ['label' => 'Seq', 'icon' => '🔢'],
    'functions' => ['label' => 'Func', 'icon' => '⚙️'],
    'sql' => ['label' => 'SQL', 'icon' => '💻'],
    'import' => ['label' => 'Import', 'icon' => '📥'],
];
?>

<div class="toolbar">
    <?php if (!isReadOnly()): ?>
    <button class="btn btn-primary" onclick="openModal('createDbModal')">+ สร้างฐานข้อมูล</button>
    <?php endif; ?>
    <a href="app.php?page=backup" class="btn btn-outline">💾 <?= e(__('export.backup')) ?></a>
    <span class="record-count">Database ปัจจุบัน: <strong><?= e($currentDb) ?></strong></span>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="data-table db-table">
            <thead>
                <tr>
                    <th>ชื่อ</th>
                    <th>Owner</th>
                    <th>Encoding</th>
                    <th>ขนาด</th>
                    <th>Conn.</th>
                    <th>เครื่องมือ — คลิกเพื่อเข้าใช้งานทันที</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($databases as $db): ?>
                <?php $isActive = $db['name'] === $currentDb; ?>
                <tr class="<?= $isActive ? 'row-active' : '' ?>">
                    <td>
                        <strong><?= e($db['name']) ?></strong>
                        <?php if ($isActive): ?><span class="badge badge-active">ใช้งานอยู่</span><?php endif; ?>
                    </td>
                    <td><?= e($db['owner']) ?></td>
                    <td><?= e($db['encoding']) ?></td>
                    <td><?= e($db['size']) ?></td>
                    <td><?= e((string) $db['connections']) ?></td>
                    <td>
                        <div class="db-tools">
                            <?php foreach ($dbTools as $page => $tool): ?>
                            <a href="<?= e(dbGoUrl($db['name'], $page)) ?>"
                               class="btn btn-sm btn-tool"
                               title="<?= e($tool['label']) ?> ใน <?= e($db['name']) ?>">
                                <span><?= $tool['icon'] ?></span> <?= e($tool['label']) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td class="actions">
                        <?php if (!$isActive && !isReadOnly()): ?>
                        <form method="POST" action="app.php?page=databases&action=drop" style="display:inline"
                              onsubmit="return confirm('ยืนยันลบฐานข้อมูล <?= e($db['name']) ?>?')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="name" value="<?= e($db['name']) ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="ลบ">✕</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="createDbModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>สร้างฐานข้อมูลใหม่</h3>
            <button class="modal-close" onclick="closeModal('createDbModal')">&times;</button>
        </div>
        <form method="POST" action="app.php?page=databases&action=create">
            <?php csrfField(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>ชื่อฐานข้อมูล</label>
                    <input type="text" name="name" required pattern="[a-zA-Z_][a-zA-Z0-9_]*">
                </div>
                <div class="form-group">
                    <label>Owner (ไม่บังคับ)</label>
                    <input type="text" name="owner">
                </div>
                <div class="form-group">
                    <label>Encoding</label>
                    <select name="encoding">
                        <option value="UTF8">UTF8</option>
                        <option value="LATIN1">LATIN1</option>
                        <option value="SQL_ASCII">SQL_ASCII</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createDbModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">สร้าง</button>
            </div>
        </form>
    </div>
</div>
