jQuery(document).ready(function ($) {

    $('.lumn-maintenance-checkbox').on('change', function () {
        var $checkbox = $(this);
        var taskId    = $checkbox.data('task-id');
        var completed = $checkbox.is(':checked');
        var $row      = $checkbox.closest('tr');

        $checkbox.prop('disabled', true);

        $.post(lumnMaintenance.ajaxUrl, {
            action:    'lumn_save_maintenance_task',
            nonce:     lumnMaintenance.nonce,
            task_id:   taskId,
            completed: completed ? '1' : '0'
        })
        .done(function (response) {
            if (response.success && response.data) {
                var d = response.data;

                $row.css('background-color', d.bg_color);
                $row.find('.lumn-maintenance-status').text(d.status_label);
                $row.find('.lumn-maintenance-last-checked').text(d.last_checked);
                $row.find('.lumn-maintenance-next-due').text(d.next_due);
            } else {
                $checkbox.prop('checked', !completed);
                alert('Error saving task. Please try again.');
            }
        })
        .fail(function () {
            $checkbox.prop('checked', !completed);
            alert('Error saving task. Please try again.');
        })
        .always(function () {
            $checkbox.prop('disabled', false);
        });
    });

});
