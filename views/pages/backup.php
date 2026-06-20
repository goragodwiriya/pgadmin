<?php
$database = $data['database'];
?>

<div class="card">
    <div class="card-header"><h3><?= e(__('export.backup')) ?></h3></div>
    <div class="card-body">
        <p>ส่งออก schema และข้อมูลทั้งหมดของฐานข้อมูล <strong><?= e($database) ?></strong> เป็นไฟล์ SQL</p>
        <div class="toolbar">
            <a href="app.php?page=export&amp;action=database" class="btn btn-primary">
                ⬇ <?= e(__('export.backup')) ?> (.sql)
            </a>
        </div>
        <ul class="help-list" style="margin-top:16px">
            <li>รวม CREATE SCHEMA, CREATE TABLE และ INSERT สำหรับทุกตาราง</li>
            <li>เหมาะสำหรับ backup ขนาดเล็กถึงกลาง (ไม่ใช่ pg_dump แบบเต็มรูปแบบ)</li>
            <li>สำหรับ production ขนาดใหญ่ แนะนำใช้ <code>pg_dump</code> โดยตรง</li>
        </ul>
    </div>
</div>
