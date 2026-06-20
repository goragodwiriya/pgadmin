<form method="POST" action="app.php?page=import" enctype="multipart/form-data">
    <?php csrfField(); ?>
    <div class="card">
        <div class="card-header"><h3>Import SQL</h3></div>
        <div class="card-body">
            <div class="form-group">
                <label>อัปโหลดไฟล์ .sql</label>
                <input type="file" name="sql_file" accept=".sql,.txt">
            </div>
            <div class="form-group">
                <label>หรือวาง SQL ด้านล่าง</label>
                <textarea name="sql_text" class="sql-editor" rows="15" placeholder="CREATE TABLE ...&#10;INSERT INTO ..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Import</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header"><h3>คำแนะนำ</h3></div>
    <div class="card-body">
        <ul class="help-list">
            <li>รองรับไฟล์ .sql ที่มีหลายคำสั่งคั่นด้วย semicolon (;)</li>
            <li>หากมี error ระหว่าง import จะ rollback ทั้งหมด</li>
            <li>คำสั่ง COMMENT และ blank line จะถูกข้าม</li>
        </ul>
    </div>
</div>
