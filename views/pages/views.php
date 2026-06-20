<?php $views = $data['views']; ?>

<div class="card">
    <div class="card-body table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>Schema</th><th>ชื่อ View</th></tr>
            </thead>
            <tbody>
                <?php if (empty($views)): ?>
                <tr><td colspan="2" class="empty">ไม่พบ View</td></tr>
                <?php endif; ?>
                <?php foreach ($views as $v): ?>
                <tr>
                    <td><code><?= e($v['schema']) ?></code></td>
                    <td><strong><?= e($v['name']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
