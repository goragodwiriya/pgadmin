<?php $functions = $data['functions']; ?>

<div class="card">
    <div class="card-body table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Schema</th>
                    <th>ชื่อ</th>
                    <th>Arguments</th>
                    <th>Return Type</th>
                    <th>Language</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($functions)): ?>
                <tr><td colspan="5" class="empty">ไม่พบ Function</td></tr>
                <?php endif; ?>
                <?php foreach ($functions as $f): ?>
                <tr>
                    <td><code><?= e($f['schema']) ?></code></td>
                    <td><strong><?= e($f['name']) ?></strong></td>
                    <td><code><?= e($f['arguments']) ?></code></td>
                    <td><code><?= e($f['return_type']) ?></code></td>
                    <td><?= e($f['language']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
