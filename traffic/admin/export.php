<?php
/**
 * Xuất danh sách vi phạm ra file CSV (mở được bằng Excel).
 * Nhận cùng bộ lọc với trang violations.php (q, status, from, to).
 */
require_once __DIR__ . '/../includes/init.php';
require_staff();

$where = ['1=1'];
$params = [];
$keyword = input('q');
$status = input('status');
if ($keyword !== '') {
    $where[] = '(v.code LIKE ? OR ' . sql_plate('vh.plate_number') . ' LIKE ? OR vh.owner_name LIKE ? OR v.location LIKE ?)';
    $like = '%' . $keyword . '%';
    array_push($params, $like, '%' . normalize_plate($keyword) . '%', $like, $like);
}
if ($status === 'qua_han') {
    $where[] = "v.status = 'chua_nop' AND v.due_date < CURDATE()";
} elseif (isset(violation_statuses()[$status])) {
    $where[] = 'v.status = ?';
    $params[] = $status;
}
if (input('from') !== '') {
    $where[] = 'DATE(v.violation_time) >= ?';
    $params[] = input('from');
}
if (input('to') !== '') {
    $where[] = 'DATE(v.violation_time) <= ?';
    $params[] = input('to');
}

$rows = fetch_all('SELECT v.*, vh.plate_number, vh.owner_name, vh.owner_cccd, vt.name AS type_name
    FROM violations v JOIN vehicles vh ON vh.id = v.vehicle_id JOIN violation_types vt ON vt.id = v.violation_type_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY v.violation_time DESC', $params);

log_activity('Xuất CSV vi phạm', count($rows) . ' dòng');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="vi-pham-' . date('Ymd-His') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM để Excel hiển thị đúng tiếng Việt
fputcsv($out, ['Mã biên bản', 'Biển số', 'Chủ xe', 'CCCD', 'Lỗi vi phạm', 'Thời gian', 'Địa điểm', 'Tiền phạt', 'Hạn nộp',
    'Số ngày quá hạn', 'Tiền chậm nộp', 'Tổng phải nộp', 'Trạng thái']);
foreach ($rows as $v) {
    $f = calc_fine($v);
    fputcsv($out, [$v['code'], $v['plate_number'], $v['owner_name'], $v['owner_cccd'], $v['type_name'], fmt_datetime($v['violation_time']),
        $v['location'], $f['fine'], fmt_date($v['due_date']), $f['late_days'], $f['late_fee'], $f['total'], violation_statuses()[$v['status']][0]]);
}
fclose($out);
