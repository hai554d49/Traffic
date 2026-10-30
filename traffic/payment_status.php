<?php
/**
 * API JSON: trả về trạng thái giao dịch theo token (trang pay.php gọi định kỳ).
 */
require_once __DIR__ . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$payment = fetch_one('SELECT status, transaction_code FROM payments WHERE token = ?', [input('token')]);
if (!$payment) {
    http_response_code(404);
    echo json_encode(['status' => 'not_found']);
    exit;
}
echo json_encode([
    'status'      => $payment['status'],
    'receipt_url' => $payment['status'] === 'thanh_cong' ? url('receipt.php?tx=' . urlencode($payment['transaction_code'])) : null,
]);
