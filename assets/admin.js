(function ($) {
    $(document).ready(function () {
        let frame;

        // Add new logo
        $('#glc-add-logo').on('click', function (e) {
            e.preventDefault();
            openMediaFrame(function (attachment) {
                addLogo(attachment);
            });
        });

        // Change existing logo
        $(document).on('click', '.glc-change-logo', function (e) {
            e.preventDefault();
            const $item = $(this).closest('.glc-logo-item');
            openMediaFrame(function (attachment) {
                $item.find('input[name*="[id]"]').val(attachment.id);
                $item.find('.glc-thumb').attr('src', attachment.url);
            });
        });

        // Remove logo
        $(document).on('click', '.glc-remove-logo', function (e) {
            e.preventDefault();
            $(this).closest('.glc-logo-item').remove();
        });

        // Save settings (AJAX)
        $('#glc-save').on('click', function (e) {
            e.preventDefault();
            const formData = $('#glc-form').serialize();
            $.post(glcAdmin.ajax_url, formData + '&action=glc_save_settings&nonce=' + glcAdmin.nonce, function (res) {
                if (res.success) {
                    alert('Settings saved');
                    location.reload();
                } else {
                    alert('Error: ' + res.data);
                }
            });
        });

        // Export JSON
        $('#glc-export-json').on('click', function () {
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(glcAdmin.settings));
            const a = document.createElement('a');
            a.setAttribute('href', dataStr);
            a.setAttribute('download', 'glc-settings.json');
            document.body.appendChild(a);
            a.click();
            a.remove();
        });

        // Import JSON
        $('#glc-import-json').on('click', function () {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'application/json';
            input.onchange = function (e) {
                const file = e.target.files[0];
                const fd = new FormData();
                fd.append('action', 'glc_import_json');
                fd.append('nonce', glcAdmin.nonce);
                fd.append('file', file);

                $.ajax({
                    url: glcAdmin.ajax_url,
                    method: 'POST',
                    processData: false,
                    contentType: false,
                    data: fd,
                    success: function (res) {
                        if (res.success) {
                            alert('Imported successfully. Reloading...');
                            location.reload();
                        } else {
                            alert('Error: ' + res.data);
                        }
                    }
                });
            };
            input.click();
        });

        // ============ Helpers =============
        function openMediaFrame(callback) {
            if (frame) {
                frame.open();
                return;
            }
            frame = wp.media({
                title: 'Select or Upload Logo',
                button: { text: 'Use this logo' },
                multiple: false
            });
            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                callback(attachment);
            });
            frame.open();
        }

        function addLogo(attachment) {
            const index = $('#glc-logos .glc-logo-item').length;
            const html = `
                <div class="glc-logo-item" data-index="${index}">
                    <input type="hidden" name="logos[${index}][id]" value="${attachment.id}" />
                    <p><img src="${attachment.url}" class="glc-thumb" style="max-width:120px;max-height:60px;" /></p>
                    <p>Link: <input type="url" name="logos[${index}][link]" style="width:60%" /></p>
                    <p><button type="button" class="button glc-change-logo">Change</button>
                    <button type="button" class="button glc-remove-logo">Remove</button></p>
                </div>`;
            $('#glc-logos').append(html);
        }
    });
})(jQuery);
