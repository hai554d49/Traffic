<div class="clearfix"></div>
<!-- footer section start -->
<div class="footer_section">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-4">
                <h2 class="useful_text">Liên kết nhanh</h2>
                <div class="footer_menu">
                    <ul>
                        <li><a href="<?= url('index.php') ?>">Trang chủ</a></li>
                        <li><a href="<?= url('lookup.php') ?>">Tra cứu phạt nguội</a></li>
                        <li><a href="<?= url('scan.php') ?>">Quét QR thanh toán</a></li>
                        <li><a href="<?= url('my_complaints.php') ?>">Khiếu nại của tôi</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4 col-md-4">
                <h2 class="useful_text">Quy định nộp phạt</h2>
                <p class="footer_text w-100">
                    Thời hạn nộp phạt: <?= (int) setting('payment_deadline_days', 10) ?> ngày kể từ ngày vi phạm.
                    Quá hạn tính thêm <?= e(setting('late_fee_rate', 0.05)) ?>%/ngày trên số tiền phạt.
                </p>
            </div>
            <div class="col-lg-4 col-md-4">
                <h2 class="useful_text">Liên hệ</h2>
                <p class="footer_text w-100"><i class="fa fa-phone"></i> Hotline: <?= e(setting('hotline')) ?><br>
                    <i class="fa fa-university"></i> <?= e(setting('bank_id')) ?> - <?= e(setting('bank_account_no')) ?><br>
                    <?= e(setting('bank_account_name')) ?></p>
            </div>
        </div>
    </div>
</div>
<div class="copyright_section">
    <div class="container">
        <p class="copyright_text"><?= date('Y') ?> <?= APP_NAME ?> - Đồ án môn Lập trình Web. Giao diện dựa trên template
            <a href="https://html.design">Free html Templates</a></p>
    </div>
</div>
<!-- footer section end -->
<script src="<?= url('js/jquery.min.js') ?>"></script>
<script src="<?= url('js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url('js/app.js') ?>"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
