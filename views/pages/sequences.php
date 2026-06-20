<?php $sequences = $data['sequences']; ?>

<div class="card">
    <div class="card-body table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Schema</th>
                    <th>ชื่อ</th>
                    <th>ชนิด</th>
                    <th>Start</th>
                    <th>Min</th>
                    <th>Max</th>
                    <th>Increment</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sequences)): ?>
                <tr><td colspan="7" class="empty">ไม่พบ Sequence</td></tr>
                <?php endif; ?>
                <?php foreach ($sequences as $s): ?>
                <tr>
                    <td><code><?= e($s['schema']) ?></code></td>
                    <td><strong><?= e($s['name']) ?></strong></td>
                    <td><?= e($s['data_type']) ?></td>
                    <td><?= e((string) $s['start_value']) ?></td>
                    <td><?= e((string) $s['minimum_value']) ?></td>
                    <td><?= e((string) $s['maximum_value']) ?></td>
                    <td><?= e((string) $s['increment']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
