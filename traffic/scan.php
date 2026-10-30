<?php
/**
 * Quét mã QR trên thông báo vi phạm / biên lai bằng camera (hoặc tải ảnh lên) ngay trên trình duyệt.
 * Thư viện: html5-qrcode.
 */
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Quét mã QR';
$activeMenu = 'scan';
require __DIR__ . '/includes/header.php';
?>
<div class="page-banner">
    <div class="container">
        <h1>Quét mã QR</h1>
        <p>Quét mã QR in trên thông báo vi phạm để mở trang nộp phạt</p>
    </div>
</div>
<div class="page-content">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card card-app">
                    <div class="card-header"><i class="fa fa-qrcode"></i> Camera / Ảnh chụp mã QR</div>
                    <div class="card-body text-center">
                        <div id="qr-reader"></div>
                        <div id="scan-result" class="mt-3"></div>
                        <p class="small text-muted mt-3 mb-0">Trình duyệt cần quyền truy cập camera. Camera chỉ hoạt động trên <code>localhost</code> hoặc HTTPS.
                            Bạn cũng có thể chọn "Scan an Image File" để tải ảnh chụp mã QR.</p>
                    </div>
                </div>
                <div class="card card-app">
                    <div class="card-body">
                        <form action="lookup.php" class="form-row">
                            <div class="col-8"><input name="q" class="form-control" placeholder="Hoặc nhập mã biên bản (vd: VP00000002)" required></div>
                            <div class="col-4"><button class="btn btn-app btn-block">Tìm</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php ob_start(); ?>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    (function () {
        var result = document.getElementById('scan-result');

        function handle(text) {
            // QR nội bộ hệ thống: chuyển thẳng tới trang tương ứng
            var m = text.match(/(pay|violation|notice)\.php\?code=([A-Z0-9]+)/i);
            var r = text.match(/(receipt\.php\?tx=[A-Z0-9]+|pay_gateway\.php\?token=[a-f0-9]+)/i);
            if (m) {
                window.location = <?= json_encode(url('')) ?> + 'pay.php?code=' + encodeURIComponent(m[2]);
            } else if (r) {
                window.location = <?= json_encode(url('')) ?> + r[1];
            } else if (/^VP\d+$/i.test(text.trim())) {
                window.location = <?= json_encode(url('violation.php?code=')) ?> + encodeURIComponent(text.trim());
            } else {
                result.innerHTML = '<div class="alert alert-warning">Mã QR không thuộc hệ thống: <code></code></div>';
                result.querySelector('code').textContent = text;
            }
        }

        var scanner = new Html5QrcodeScanner('qr-reader', { fps: 10, qrbox: 250, rememberLastUsedCamera: true }, false);
        scanner.render(function (decodedText) {
            scanner.clear();
            result.innerHTML = '<div class="alert alert-success">Đã đọc mã, đang chuyển trang...</div>';
            handle(decodedText);
        }, function () { /* bỏ qua lỗi từng khung hình */ });
    })();
</script>
<?php
$extraScripts = ob_get_clean();
require __DIR__ . '/includes/footer.php';
