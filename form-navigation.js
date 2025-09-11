// Multi-step form navigation and validation
jQuery(document).ready(function($) {
    var formStarted = false;
    var originalBeforeUnload = window.onbeforeunload;
    
    // Simple function to check if we're on step 4
    function checkStep4() {
        var isStep4 = $('.step[data-step="4"]').hasClass('active');
        if (isStep4) {
            $('.nav-btn.next-btn, .nav-btn.submit-btn').css({
                'display': 'flex !important',
                'visibility': 'visible !important',
                'opacity': '1 !important'
            });
        }
    }
    
    // Run check immediately and then periodically
    checkStep4();
    setInterval(checkStep4, 1000);
    
    // Ensure final declaration checkbox is unchecked by default
    function forceUncheckDeclaration() {
        $('#final_declaration').prop('checked', false);
        $('#final_declaration').attr('checked', false);
        $('#final_declaration').removeAttr('checked');
        
        // Also uncheck any other final checkboxes
        $('input[name="final_declaration"]').prop('checked', false);
        $('input[name="final_declaration"]').attr('checked', false);
        $('input[name="final_declaration"]').removeAttr('checked');
        
        // Force visual update
        $('#final_declaration').trigger('change');
    }
    
    // Run multiple times to ensure it takes effect
    forceUncheckDeclaration();
    setTimeout(forceUncheckDeclaration, 100);
    setTimeout(forceUncheckDeclaration, 500);
    setTimeout(forceUncheckDeclaration, 1000);
    
    // Also run when page becomes visible
    $(document).on('visibilitychange', function() {
        if (!document.hidden) {
            setTimeout(forceUncheckDeclaration, 100);
        }
    });
    
    // Combine date fields into hidden birth_date field
    function updateBirthDate() {
        var day = $('#birth_day').val();
        var month = $('#birth_month').val();
        var year = $('#birth_year').val();
        
        if (day && month && year) {
            var birthDate = year + '-' + month.padStart(2, '0') + '-' + day.padStart(2, '0');
            $('#birth_date').val(birthDate);
        } else {
            $('#birth_date').val('');
        }
    }
    
    // Track form interaction
    $('#acf-quiz-form input, #acf-quiz-form select, #acf-quiz-form textarea').on('input change', function() {
        formStarted = true;
    });
    
    // Remove error class from all fields on page load and ensure clean styling
    function resetFieldStyling() {
        $('#acf-quiz-form input, #acf-quiz-form select, #acf-quiz-form textarea').each(function() {
            $(this).removeClass('error touched invalid');
            this.setCustomValidity('');
            
            // Remove any existing error styling
            $(this).css({
                'border-color': '',
                'background-color': '',
                'box-shadow': ''
            });
            
            // Force clean styling
            this.style.setProperty('border-color', '#ddd', 'important');
            this.style.setProperty('background-color', '#fff', 'important');
        });
    }
    
    // Run multiple times to ensure complete override
    resetFieldStyling();
    setTimeout(resetFieldStyling, 100);
    setTimeout(resetFieldStyling, 500);
    
    // Add touched class to invalid fields on blur
    $('#acf-quiz-form input, #acf-quiz-form select, #acf-quiz-form textarea').on('blur', function() {
        if ($(this).is(':invalid')) {
            $(this).addClass('touched');
        }
    });
    
    // Remove touched class when field becomes valid
    $('#acf-quiz-form input, #acf-quiz-form select, #acf-quiz-form textarea').on('input change', function() {
        if ($(this).is(':valid')) {
            $(this).removeClass('touched');
        }
    });
    
    $('#birth_day, #birth_month, #birth_year').on('change', updateBirthDate);
    
    // Navigation warning
    window.addEventListener('beforeunload', function(e) {
        console.log('beforeunload triggered - formStarted:', formStarted, 'formSubmitting:', formSubmitting, 'window.formSubmitting:', window.formSubmitting);
        
        if (formStarted && !formSubmitting && !window.formSubmitting) {
            var message = 'יש לך שינויים שלא נשמרו. האם אתה בטוח שברצונך לעזוב?';
            e.preventDefault();
            e.returnValue = message;
            return message;
        } else {
            console.log('Allowing navigation - no confirmation needed');
        }
    });

    window.addEventListener('popstate', function(e) {
        console.log('popstate triggered - formStarted:', formStarted, 'formSubmitting:', formSubmitting, 'window.formSubmitting:', window.formSubmitting);
        
        if (formStarted && !formSubmitting && !window.formSubmitting) {
            var shouldLeave = confirm('יש לך שינויים שלא נשמרו. האם אתה בטוח שברצונך לעזוב?');
            if (!shouldLeave) {
                // Push the current state back to prevent navigation
                history.pushState(null, null, window.location.href);
                e.preventDefault();
                return false;
            } else {
                console.log('User confirmed navigation');
            }
        } else {
            console.log('Allowing popstate navigation - no confirmation needed');
        }
    });
    
    // Track form submission to disable navigation warning
    var formSubmitting = false;
    window.formSubmitting = false;
    
    $('#acf-quiz-form').on('submit', function() {
        console.log('Form submission started - disabling navigation warning');
        formSubmitting = true;
        window.formSubmitting = true;
    });
});
