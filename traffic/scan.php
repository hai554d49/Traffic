<?php
/**
 * QUÉT MÃ QR bằng camera (thư viện html5-qrcode, chạy trên trình duyệt).
 * Đọc được mã trên thông báo vi phạm / biên lai thì chuyển thẳng tới trang tương ứng.
 */
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/layout.php';

page_header('Quét mã QR', 'scan');
?>
<div class="page-banner"><div class="container"><h1>Quét mã QR</h1><p>Quét mã QR in trên thông báo vi phạm để mở trang nộp phạt</p></div></div>
<div class="page-content"><div class="container"><div class="row justify-content-center"><div class="col-lg-7">
    <div class="card card-app">
        <div class="card-header"><i class="fa fa-qrcode"></i> Camera / Ảnh chụp mã QR</div>
        <div class="card-body text-center">
            <div id="qr-reader"></div>
            <div id="scan-result" class="mt-3"></div>
            <p class="small text-muted mt-3 mb-0">Trình duyệt cần quyền truy cập camera. Camera chỉ hoạt động trên <code>localhost</code> hoặc HTTPS.
                Bạn cũng có thể chọn "Scan an Image File" để tải ảnh chụp mã QR.</p>
        </div>
    </div>
    <div class="card card-app"><div class="card-body">
        <form action="<?= url('index.php') ?>" class="form-row">
            <div class="col-8"><input name="q" class="form-control" placeholder="Hoặc nhập mã biên bản (vd: VP00000002)" required></div>
            <div class="col-4"><button class="btn btn-app btn-block">Tìm</button></div>
        </form>
    </div></div>
</div></div></div></div>
<?php ob_start(); ?>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    (function () {
        var base = <?= json_encode(url('')) ?>;
        var result = document.getElementById('scan-result');
        function handle(text) {
            var m = text.match(/pay\.php\?(code|token|receipt)=([A-Za-z0-9]+)/);
            if (m) { window.location = base + 'pay.php?' + m[1] + '=' + encodeURIComponent(m[2]); return; }
            if (/^VP\d+$/i.test(text.trim())) { window.location = base + 'violation.php?code=' + encodeURIComponent(text.trim()); return; }
            result.innerHTML = '<div class="alert alert-warning">Mã QR không thuộc hệ thống: <code></code></div>';
            result.querySelector('code').textContent = text;
        }
        var scanner = new Html5QrcodeScanner('qr-reader', { fps: 10, qrbox: 250, rememberLastUsedCamera: true }, false);
        scanner.render(function (decodedText) {
            scanner.clear();
            result.innerHTML = '<div class="alert alert-success">Đã đọc mã, đang chuyển trang...</div>';
            handle(decodedText);
        }, function () {});
    })();
</script>
<?php
page_footer(ob_get_clean());
