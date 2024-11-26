$(function () {
    $('.field-file').each(function () {
        $(this).find('.form-control').on('change', function (e) {
            const file = e.target.files[0];

            if (file.size < 500000) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    $('.field-textarea :input').val(event.target.result);
                };
                reader.readAsDataURL(file);
            } else {
                $('.field-textarea :input').val('File is too big! Max size is 500kB');
            }
        });
    });
});
