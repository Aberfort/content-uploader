jQuery(document).ready(function ($) {
    $('#content-uploader-form').on('submit', function (e) {
        e.preventDefault();

        var $submitButton = $(this).find('button[type="submit"]');
        $submitButton.prop('disabled', true);

        var formData = new FormData(this);
        formData.append('action', 'content_uploader_upload');
        formData.append('content_uploader_nonce', ContentUploaderAjax.nonce);

        $.ajax({
            url: ContentUploaderAjax.ajax_url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function () {
                $('#import-progress').show();
                $('#progress-bar').css('width', '0%');
                $('#progress-text').text('0%');
                $('#current-batch').text('');
                $('#cu-console').html('<p class="info">Starting import...</p>');
                $('#spinner').show();
            },
            success: function (response) {
                if (response.success) {
                    if (response.data.status && response.data.status === 'warning') {
                        alert(response.data.message);
                        $('#cu-console').append('<p class="warning">' + response.data.message + '</p>');
                        $('#import-progress').hide();
                        $('#spinner').hide();
                        $submitButton.prop('disabled', false);
                        return;
                    }

                    var messages = response.data.message;
                    if (Array.isArray(messages)) {
                        messages.forEach(function (msg) {
                            $('#cu-console').append('<p class="info">' + msg + '</p>');
                        });
                    } else {
                        $('#cu-console').append('<p class="info">' + messages + '</p>');
                    }

                    if (response.data.need_reupload) {
                        alert(response.data.message);
                        $('#import-progress').hide();
                        $('#spinner').hide();
                        $submitButton.prop('disabled', false);
                        return;
                    }

                    var total = response.data.total;
                    var batch_size = 1;
                    var total_batches = Math.ceil(total / batch_size);
                    if (total === 0) {
                        $('#progress-bar').css('width', '100%');
                        $('#progress-text').text('100%');
                        $('#current-batch').text('');
                        $('#cu-console').append('<p class="success">No entries to process.</p>');
                        alert('Import completed successfully.');
                        $('#spinner').hide();
                        $submitButton.prop('disabled', false);
                        return;
                    }
                    processBatch(1, total_batches, total);
                } else {
                    $('#cu-console').append('<p class="error">' + response.data + '</p>');
                    alert(response.data);
                    $('#import-progress').hide();
                    $('#spinner').hide();
                    $submitButton.prop('disabled', false);
                }
            },
            error: function () {
                alert('Error uploading file.');
                $('#import-progress').hide();
                $('#spinner').hide();
                $submitButton.prop('disabled', false);
            }
        });
    });

    function processBatch(currentBatch, totalBatches, totalEntries) {
        $('#current-batch').text('Processing batch ' + currentBatch + ' of ' + totalBatches + '...');

        $.ajax({
            url: ContentUploaderAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'content_uploader_import_batch',
                content_uploader_nonce: ContentUploaderAjax.nonce,
                batch: currentBatch - 1
            },
            success: function (response) {
                if (response.success) {
                    var messages = response.data.message;
                    var processed = response.data.processed;
                    var completed = response.data.completed;

                    if (!Array.isArray(messages)) {
                        console.error('Expected messages to be an array.');
                        alert('Unexpected server response.');
                        $('#import-progress').hide();
                        $('#spinner').hide();
                        $('button[type="submit"]').prop('disabled', false);
                        return;
                    }

                    var percentage = Math.min((processed / totalEntries) * 100, 100);
                    $('#progress-bar').css('width', percentage + '%');
                    $('#progress-text').text(Math.floor(percentage) + '%');

                    messages.forEach(function (message) {
                        var messageClass = 'info';
                        if (message.toLowerCase().includes('error')) {
                            messageClass = 'error';
                        } else if (message.toLowerCase().includes('success') || completed) {
                            messageClass = 'success';
                        }
                        $('#cu-console').append('<p class="' + messageClass + '">' + message + '</p>');
                    });
                    $('#cu-console').scrollTop($('#cu-console')[0].scrollHeight);

                    if (!completed) {
                        processBatch(currentBatch + 1, totalBatches, totalEntries);
                    } else {
                        $('#progress-bar').css('width', '100%');
                        $('#progress-text').text('100%');
                        $('#current-batch').text('Completed');
                        $('#cu-console').append('<p class="success">Import completed successfully.</p>');
                        alert('Import completed successfully.');
                        $('#spinner').hide();
                        $('button[type="submit"]').prop('disabled', false);
                    }
                } else {
                    $('#cu-console').append('<p class="error">' + response.data + '</p>');
                    alert(response.data);
                    $('#import-progress').hide();
                    $('#spinner').hide();
                    $('button[type="submit"]').prop('disabled', false);
                }
            },
            error: function () {
                alert('Error processing batch.');
                $('#import-progress').hide();
                $('#spinner').hide();
                $('button[type="submit"]').prop('disabled', false);
            }
        });
    }
});
