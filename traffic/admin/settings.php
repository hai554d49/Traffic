<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

$settings = fetch_all('SELECT * FROM settings ORDER BY setting_key');
$numeric = ['payment_deadline_days', 'late_fee_rate', 'late_fee_cap_percent', 'qr_expire_minutes'];
$errors = [];

if (is_post()) {
    verify_csrf();
    $values = $_POST['settings'] ?? [];
    foreach ($settings as $s) {
        $key = $s['setting_key'];
        $value = trim((string) ($values[$key] ?? ''));
        if ($value === '') {
            $errors[] = 'Vui lòng nhập "' . $s['label'] . '".';
        } elseif (in_array($key, $numeric, true) && (!is_numeric($value) || $value < 0)) {
            $errors[] = '"' . $s['label'] . '" phải là số không âm.';
        }
    }
    if (!$errors) {
        foreach ($settings as $s) {
            query('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [trim($values[$s['setting_key']]), $s['setting_key']]);
        }
        log_activity('Cập nhật cấu hình');
        flash('success', 'Đã lưu cấu hình. Tiền chậm nộp của các biên bản chưa nộp được tính lại theo cấu hình mới.');
        redirect('admin/settings.php');
    }
}

$pageTitle = 'Cấu hình hệ thống';
$activeMenu = 'settings';
require __DIR__ . '/includes/header.php';

// Ví dụ minh họa cách tính lãi
$exFine = 1000000;
$exDays = 20;
$exLate = min(round($exFine * setting('late_fee_rate') / 100 * $exDays), round($exFine * setting('late_fee_cap_percent') / 100));
?>
<div class="row">
    <div class="col-lg-7">
        <div class="card card-app">
            <div class="card-header">Tham số</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <?php foreach ($settings as $s): ?>
                        <div class="form-group">
                            <label><?= e($s['label']) ?></label>
                            <input name="settings[<?= e($s['setting_key']) ?>]" class="form-control"
                                   value="<?= e($_POST['settings'][$s['setting_key']] ?? $s['setting_value']) ?>"
                                <?= in_array($s['setting_key'], $numeric, true) ? 'type="number" step="any" min="0"' : '' ?>>
                        </div>
                    <?php endforeach; ?>
                    <button class="btn btn-app"><i class="fa fa-save"></i> Lưu cấu hình</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-app">
            <div class="card-header">Cách tính tiền chậm nộp</div>
            <div class="card-body">
                <p><code>Tiền chậm nộp = Tiền phạt × <?= e(setting('late_fee_rate')) ?>% × Số ngày quá hạn</code></p>
                <p>Tối đa không quá <?= e(setting('late_fee_cap_percent')) ?>% số tiền phạt. Trong thời gian khiếu nại, tiền chậm nộp tạm dừng và hạn nộp được gia hạn tương ứng.</p>
                <div class="amount-box">
                    <b>Ví dụ:</b> phạt <?= money($exFine) ?>, quá hạn <?= $exDays ?> ngày<br>
                    Chậm nộp = <?= money($exLate) ?><br>
                    Tổng phải nộp = <b><?= money($exFine + $exLate) ?></b>
                </div>
            </div>
        </div>
        <div class="card card-app">
            <div class="card-header">Xem trước mã VietQR</div>
            <div class="card-body text-center">
                <img src="<?= e(vietqr_image_url(100000, 'VPTEST')) ?>" alt="VietQR" class="img-fluid" style="max-width:220px">
                <p class="small text-muted mt-2 mb-0">Mã QR chuyển khoản 100.000đ tới tài khoản đã cấu hình. Quét bằng app ngân hàng để kiểm tra.</p>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
