<?php $roles = $data['roles']; ?>

<div class="toolbar">
    <button class="btn btn-primary" onclick="openModal('createRoleModal')">+ สร้าง Role</button>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ชื่อ</th>
                    <th>Superuser</th>
                    <th>Login</th>
                    <th>Create DB</th>
                    <th>Create Role</th>
                    <th>Conn Limit</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $role): ?>
                <tr>
                    <td><strong><?= e($role['name']) ?></strong></td>
                    <td><?= $role['is_superuser'] ? '✓' : '' ?></td>
                    <td><?= $role['can_login'] ? '✓' : '' ?></td>
                    <td><?= $role['can_create_db'] ? '✓' : '' ?></td>
                    <td><?= $role['can_create_role'] ? '✓' : '' ?></td>
                    <td><?= e((string) ($role['connection_limit'] ?? '-1')) ?></td>
                    <td>
                        <form method="POST" action="app.php?page=roles&action=drop" style="display:inline"
                              onsubmit="return confirm('ยืนยันลบ Role <?= e($role['name']) ?>?')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="name" value="<?= e($role['name']) ?>">
                            <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="createRoleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>สร้าง Role ใหม่</h3>
            <button class="modal-close" onclick="closeModal('createRoleModal')">&times;</button>
        </div>
        <form method="POST" action="app.php?page=roles&action=create">
            <?php csrfField(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>ชื่อ Role</label>
                    <input type="text" name="name" required pattern="[a-zA-Z_][a-zA-Z0-9_]*">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password">
                </div>
                <div class="form-checks">
                    <label><input type="checkbox" name="superuser"> Superuser</label>
                    <label><input type="checkbox" name="createdb"> Create DB</label>
                    <label><input type="checkbox" name="createrole"> Create Role</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createRoleModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">สร้าง</button>
            </div>
        </form>
    </div>
</div>
