/* JavaScript dùng chung cho hệ thống */
$(function () {
    // Hỏi xác nhận trước khi thực hiện thao tác nguy hiểm (xóa, hủy...)
    $(document).on('submit', 'form[data-confirm]', function (e) {
        if (!confirm($(this).data('confirm'))) {
            e.preventDefault();
        }
    });
    $(document).on('click', 'a[data-confirm]', function (e) {
        if (!confirm($(this).data('confirm'))) {
            e.preventDefault();
        }
    });

    // Tự ẩn thông báo sau 6 giây
    setTimeout(function () {
        $('.alert-dismissible').alert('close');
    }, 6000);

    $('[data-toggle="tooltip"]').tooltip();
});
