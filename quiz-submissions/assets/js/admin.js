jQuery(document).ready(function($) {
    'use strict';

    // Handle delete confirmation
    $('.wp-list-table').on('click', '.delete', function(e) {
        if (!confirm(quizSubmissions.are_you_sure)) {
            e.preventDefault();
            return false;
        }
    });

    // Handle bulk actions
    $('body').on('change', '#bulk-action-selector-top, #bulk-action-selector-bottom', function() {
        var action = $(this).val();
        if (action === 'delete') {
            if (!confirm(quizSubmissions.are_you_sure)) {
                $(this).val('');
                return false;
            }
        }
    });

    // Toggle row details on mobile
    $('.wp-list-table .toggle-row').on('click', function() {
        $(this).closest('tr').toggleClass('is-expanded');
    });

    // Handle search form submission
    $('#search-submissions').on('keypress', function(e) {
        if (e.which === 13) {
            $(this).closest('form').submit();
        }
    });

    // Handle AJAX actions
    $('.quiz-submission-action').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var action = $button.data('action');
        var submissionId = $button.data('id');
        var $row = $button.closest('tr');
        
        if (!action || !submissionId) {
            return;
        }
        
        // Show loading state
        $button.prop('disabled', true).addClass('updating-message');
        
        // Send AJAX request
        $.ajax({
            url: quizSubmissions.ajax_url,
            type: 'POST',
            data: {
                action: 'quiz_submission_' + action,
                submission_id: submissionId,
                nonce: quizSubmissions.nonce
            },
            dataType: 'json'
        })
        .done(function(response) {
            if (response.success) {
                if (action === 'delete') {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        // Update count
                        var count = parseInt($('.displaying-num').text()) - 1;
                        $('.displaying-num').text(count);
                    });
                } else {
                    // Handle other actions (approve, reject, etc.)
                    location.reload();
                }
            } else {
                alert(response.data || 'An error occurred');
            }
        })
        .fail(function() {
            alert('Request failed. Please try again.');
        })
        .always(function() {
            $button.prop('disabled', false).removeClass('updating-message');
        });
    });

    // Handle form submission for step 1 and step 2
    $('.quiz-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $form.find('button[type="submit"]');
        var formData = $form.serialize();
        var step = $form.data('step') || 1;
        
        // Show loading state
        $submitButton.prop('disabled', true).addClass('is-loading');
        
        // Send AJAX request
        $.ajax({
            url: quizSubmissions.ajax_url,
            type: 'POST',
            data: {
                action: 'save_quiz_step',
                step: step,
                data: formData,
                nonce: quizSubmissions.nonce
            },
            dataType: 'json'
        })
        .done(function(response) {
            if (response.success) {
                // Handle success (redirect to next step or show success message)
                if (response.data.redirect) {
                    window.location.href = response.data.redirect;
                } else if (step < 2) {
                    // Show next step
                    $form.closest('.quiz-step').removeClass('is-active');
                    $form.closest('.quiz-step').next('.quiz-step').addClass('is-active');
                } else {
                    // Show success message
                    $form.html('<div class="notice notice-success"><p>' + quizSubmissions.success_message + '</p></div>');
                }
            } else {
                // Show error message
                alert(response.data || 'An error occurred while saving your data.');
            }
        })
        .fail(function() {
            alert('Request failed. Please check your connection and try again.');
        })
        .always(function() {
            $submitButton.prop('disabled', false).removeClass('is-loading');
        });
    });

    // Handle navigation between steps
    $('.quiz-step-nav').on('click', 'button', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var target = $button.data('target');
        var $currentStep = $('.quiz-step.is-active');
        
        if (target === 'prev') {
            $currentStep.removeClass('is-active').prev('.quiz-step').addClass('is-active');
        } else if (target === 'next' && $currentStep.find('form')[0].checkValidity()) {
            $currentStep.removeClass('is-active').next('.quiz-step').addClass('is-active');
        }
    });
});
