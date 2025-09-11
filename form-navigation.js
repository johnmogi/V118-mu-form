// Multi-step form navigation
jQuery(document).ready(function($) {
    // Form Navigation
    function updateStepNavigation() {
        const currentStep = $('.form-step.active').data('step');
        const totalSteps = 5;
        
        // Update progress indicator
        $('.step-indicator .step').removeClass('active');
        $('.step-indicator .step[data-step="' + currentStep + '"]').addClass('active');
        
        // Update step title and subtitle
        const stepTitles = {
            1: 'שאלון התאמה',
            2: 'פרטים אישיים',
            3: 'שאלון התאמה - חלק א',
            4: 'שאלון התאמה - חלק ב',
            5: 'אישור והגשה'
        };
        
        const stepSubtitles = {
            1: 'שלב 1 מתוך 5',
            2: 'שלב 2 מתוך 5',
            3: 'שלב 3 מתוך 5',
            4: 'שלב 4 מתוך 5',
            5: 'שלב 5 מתוך 5 - סיום'
        };
        
        $('#step-title').text(stepTitles[currentStep]);
        $('#step-subtitle').text(stepSubtitles[currentStep]);
        
        // Update navigation buttons
        if (currentStep === 1) {
            $('.prev-btn').hide();
        } else {
            $('.prev-btn').show();
        }
        
        if (currentStep === totalSteps) {
            $('.next-btn').hide();
            $('.submit-btn').show();
        } else {
            $('.next-btn').show();
            $('.submit-btn').hide();
        }
        
        // Trigger step changed event
        $(document).trigger('formStepChanged', [currentStep]);
    }
    
    // Handle next button click
    $(document).on('click', '.next-btn', function(e) {
        e.preventDefault();
        
        const $currentStep = $('.form-step.active');
        const currentStep = $currentStep.data('step');
        let isValid = true;
        
        // Validate current step
        if (currentStep === 4) {
            // Validate step 4 (agreement, ID upload, signature)
            if (!$('#final_declaration').is(':checked')) {
                isValid = false;
                $('#final_declaration').closest('.field-group').addClass('error');
            }
            
            if (!document.getElementById('signature_data').value) {
                isValid = false;
                $('#signature_pad').closest('.signature-section').addClass('error');
            }
        } else {
            // Validate required fields in current step
            $currentStep.find('[required]').each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('error');
                }
            });
        }
        
        if (!isValid) {
            // Scroll to first error
            $('html, body').animate({
                scrollTop: $currentStep.find('.error').first().offset().top - 100
            }, 300);
            return false;
        }
        
        // Move to next step
        $currentStep.removeClass('active');
        $currentStep.next('.form-step').addClass('active');
        updateStepNavigation();
    });
    
    // Handle previous button click
    $(document).on('click', '.prev-btn', function(e) {
        e.preventDefault();
        
        const $currentStep = $('.form-step.active');
        $currentStep.removeClass('active');
        $currentStep.prev('.form-step').addClass('active');
        updateStepNavigation();
    });
    
    // Initialize navigation on page load
    updateStepNavigation();
});
