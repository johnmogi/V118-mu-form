// Submissions viewer functionality
jQuery(document).ready(function($) {
    // Select all checkbox functionality
    $('#cb-select-all-1').on('change', function() {
        $('input[name="submission_ids[]"]').prop('checked', this.checked);
    });

    // Individual checkbox change handler
    $('input[name="submission_ids[]"]').on('change', function() {
        var totalCheckboxes = $('input[name="submission_ids[]"]').length;
        var checkedCheckboxes = $('input[name="submission_ids[]"]:checked').length;
        
        $('#cb-select-all-1').prop('checked', totalCheckboxes === checkedCheckboxes);
    });

    // Bulk actions handler
    $('#doaction, #doaction2').on('click', function(e) {
        var action = $(this).siblings('select').val();
        var checkedItems = $('input[name="submission_ids[]"]:checked');
        
        if (action === '-1') {
            e.preventDefault();
            alert('אנא בחר פעולה');
            return false;
        }
        
        if (checkedItems.length === 0) {
            e.preventDefault();
            alert('אנא בחר לפחות פריט אחד');
            return false;
        }
        
        if (action === 'delete') {
            if (!confirm('האם אתה בטוח שברצונך למחוק את הפריטים הנבחרים?')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Signature modal functionality
    let currentSignatureData = '';
    let currentSubmissionId = '';
    
    // Open signature modal
    $(document).on('click', '.view-signature', function(e) {
        e.preventDefault();
        
        currentSubmissionId = $(this).data('submission-id');
        currentSignatureData = $(this).data('signature');
        
        if (!currentSignatureData) {
            alert('לא נמצאה חתימה עבור הגשה זו');
            return;
        }
        
        // Display signature in modal
        $('#signature-display').html('<img src="' + currentSignatureData + '" alt="חתימה דיגיטלית" style="max-width: 100%; height: auto; border: 1px solid #ccc;">');
        $('#signature-modal').show();
    });
    
    // Close signature modal
    $(document).on('click', '.close-modal, #signature-modal', function(e) {
        if (e.target === this) {
            $('#signature-modal').hide();
        }
    });
    
    // Download signature
    $(document).on('click', '#download-signature', function() {
        if (!currentSignatureData) {
            alert('לא נמצאה חתימה להורדה');
            return;
        }
        
        // Create download link
        const link = document.createElement('a');
        link.href = currentSignatureData;
        link.download = 'signature_' + currentSubmissionId + '.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // ESC key to close modal
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#signature-modal').hide();
        }
    });
});
