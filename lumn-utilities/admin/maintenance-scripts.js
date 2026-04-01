jQuery(document).ready(function ($) {

    var statusColors = {
        complete: '#d4edda',
        overdue:  '#f8d7da',
        due_soon: '#fff3cd',
        pending:  '#ffffff'
    };

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
            if (response.success) {
                var newStatus = completed ? 'complete' : 'overdue';
                $row.css('background-color', statusColors[newStatus]);
                $row.find('.lumn-maintenance-status').text(completed ? 'Complete' : 'Overdue');

                if (completed && response.data && response.data.last_checked) {
                    $row.find('.lumn-maintenance-last-checked').text(response.data.last_checked);
                }
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
