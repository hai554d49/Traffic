<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

$pg = paginate((int) fetch_value('SELECT COUNT(*) FROM activity_logs'), 25);
$logs = fetch_all("SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
                   ORDER BY l.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}");

$pageTitle = 'Nhật ký hoạt động';
$activeMenu = 'logs';
require __DIR__ . '/includes/header.php';
?>
<div class="table-wrap">
    <table class="table table-sm table-hover">
        <thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Hành động</th><th>Chi tiết</th><th>IP</th></tr></thead>
        <tbody>
        <?php if (!$logs): ?><tr><td colspan="5" class="text-center text-muted py-4">Chưa có hoạt động.</td></tr><?php endif; ?>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td class="text-nowrap"><?= fmt_datetime($l['created_at']) ?></td>
                <td><?= e($l['username'] ?: 'Khách') ?></td>
                <td><?= e($l['action']) ?></td>
                <td><small><?= e($l['detail']) ?></small></td>
                <td><small class="text-muted"><?= e($l['ip_address']) ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= render_pagination($pg) ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
