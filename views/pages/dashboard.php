<?php $info = $data['info']; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">PostgreSQL Version</div>
        <div class="stat-value small"><?= e(substr($info['version'], 0, 60)) ?>...</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Database ปัจจุบัน</div>
        <div class="stat-value"><?= e($info['current_database']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">User</div>
        <div class="stat-value"><?= e($info['current_user']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Connections</div>
        <div class="stat-value"><?= e((string) $info['active_connections']) ?> / <?= e((string) $info['max_connections']) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>ข้อมูลเซิร์ฟเวอร์</h3></div>
    <div class="card-body">
        <table class="info-table">
            <tr><th>Server Encoding</th><td><?= e($info['server_encoding']) ?></td></tr>
            <tr><th>Client Encoding</th><td><?= e($info['client_encoding']) ?></td></tr>
            <tr><th>Timezone</th><td><?= e($info['timezone']) ?></td></tr>
            <tr><th>Max Connections</th><td><?= e((string) $info['max_connections']) ?></td></tr>
            <tr><th>Version (Full)</th><td><code><?= e($info['version']) ?></code></td></tr>
        </table>
    </div>
</div>

<div class="quick-links">
    <a href="app.php?page=sql" class="quick-link-card">
        <span class="icon">💻</span>
        <strong>SQL Query</strong>
        <small>รัน SQL คำสั่ง</small>
    </a>
    <a href="app.php?page=tables" class="quick-link-card">
        <span class="icon">📋</span>
        <strong>ตาราง</strong>
        <small>จัดการตาราง</small>
    </a>
    <a href="app.php?page=databases" class="quick-link-card">
        <span class="icon">🗄️</span>
        <strong>ฐานข้อมูล</strong>
        <small>สร้าง/ลบ DB</small>
    </a>
    <a href="app.php?page=import" class="quick-link-card">
        <span class="icon">📥</span>
        <strong>Import</strong>
        <small>นำเข้า SQL</small>
    </a>
</div>
