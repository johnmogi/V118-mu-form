jQuery(document).ready(function($) {
    $('#acf-calculator').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var fields = {};
        
        // Get all field values
        $('.calculator-field').each(function() {
            var field = $(this);
            var fieldName = field.data('field-name');
            fields[fieldName] = field.val() || 0;
        });
        
        // Get the formula from the page (you might want to pass this via data attribute)
        var formula = ''; // This should be set from your PHP
        
        // Make AJAX request to calculate
        $.ajax({
            url: acf_calculator.ajax_url,
            type: 'POST',
            data: {
                action: 'calculate',
                nonce: acf_calculator.nonce,
                fields: fields,
                formula: formula
            },
            success: function(response) {
                if (response.success) {
                    $('#calculation-result').text(response.data);
                } else {
                    console.error('Calculation error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
            }
        });
    });
    
    // Recalculate on field change
    $('.calculator-field').on('change input', function() {
        $('#acf-calculator').trigger('submit');
    });
});
