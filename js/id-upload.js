jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize ID upload functionality
    initIDUpload();
    
    function initIDUpload() {
        const uploadInput = $('#id_photo_upload');
        const hiddenInput = $('#uploaded_id_photo');
        const progressDiv = $('.upload-progress');
        const successDiv = $('.upload-success');
        const errorDiv = $('.upload-error');
        const progressFill = $('.progress-fill');
        const progressText = $('.progress-text');
        const errorText = $('.error-text');
        
        if (!uploadInput.length) {
            return;
        }
        
        // Handle file selection
        uploadInput.on('change', function(e) {
            const file = e.target.files[0];
            
            if (!file) {
                return;
            }
            
            // Reset states
            hideAllStates();
            
            // Validate file before upload
            const validation = validateFile(file);
            if (!validation.valid) {
                showError(validation.message);
                uploadInput.val('');
                return;
            }
            
            // Show progress
            showProgress();
            
            // Upload file
            uploadFile(file);
        });
        
        function validateFile(file) {
            // Check file size (5MB)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                return {
                    valid: false,
                    message: 'הקובץ גדול מדי (מקסימום 5MB)'
                };
            }
            
            // Check file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                return {
                    valid: false,
                    message: 'סוג קובץ לא נתמך (רק JPG, PNG או PDF)'
                };
            }
            
            return {
                valid: true,
                message: 'הקובץ תקין'
            };
        }
        
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('action', 'upload_id_photo');
            formData.append('id_photo', file);
            formData.append('nonce', id_upload_ajax.nonce);
            
            // Create XMLHttpRequest for progress tracking
            const xhr = new XMLHttpRequest();
            
            // Track upload progress
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    updateProgress(percentComplete);
                }
            });
            
            // Handle response
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            showSuccess(response.data.message);
                            hiddenInput.val(response.data.filename);
                            
                            // Update file input display
                            updateFileInputDisplay(file.name);
                        } else {
                            showError(response.data || 'שגיאה בהעלאת הקובץ');
                            uploadInput.val('');
                        }
                    } catch (e) {
                        showError('שגיאה בעיבוד התגובה מהשרת');
                        uploadInput.val('');
                    }
                } else {
                    showError('שגיאת שרת (' + xhr.status + ')');
                    uploadInput.val('');
                }
            });
            
            // Handle errors
            xhr.addEventListener('error', function() {
                showError('שגיאה בחיבור לשרת');
                uploadInput.val('');
            });
            
            // Send request
            xhr.open('POST', id_upload_ajax.ajax_url);
            xhr.send(formData);
        }
        
        function updateProgress(percent) {
            progressFill.css('width', percent + '%');
            progressText.text('מעלה קובץ... ' + Math.round(percent) + '%');
        }
        
        function hideAllStates() {
            progressDiv.hide();
            successDiv.hide();
            errorDiv.hide();
        }
        
        function showProgress() {
            hideAllStates();
            progressDiv.show();
            progressFill.css('width', '0%');
            progressText.text('מעלה קובץ...');
        }
        
        function showSuccess(message) {
            hideAllStates();
            successDiv.find('.success-text').text(message);
            successDiv.show();
        }
        
        function showError(message) {
            hideAllStates();
            errorText.text(message);
            errorDiv.show();
        }
        
        function updateFileInputDisplay(filename) {
            // Create a custom display for the uploaded file
            const displayName = filename.length > 30 ? 
                filename.substring(0, 27) + '...' : 
                filename;
            
            uploadInput.after('<div class="uploaded-file-display">' +
                '<span class="file-icon">📄</span>' +
                '<span class="file-name">' + displayName + '</span>' +
                '<button type="button" class="remove-file">✗</button>' +
                '</div>');
            
            uploadInput.hide();
            
            // Handle file removal
            $('.remove-file').on('click', function() {
                $(this).parent().remove();
                uploadInput.show().val('');
                hiddenInput.val('');
                hideAllStates();
            });
        }
    }
    
    // Add validation to checkout form
    $('body').on('checkout_error', function() {
        const hiddenInput = $('#uploaded_id_photo');
        if (!hiddenInput.val()) {
            $('.id-upload-section').addClass('woocommerce-invalid');
            $('html, body').animate({
                scrollTop: $('.id-upload-section').offset().top - 100
            }, 500);
        }
    });
    
    // Remove validation error when file is uploaded
    $('#uploaded_id_photo').on('change', function() {
        if ($(this).val()) {
            $('.id-upload-section').removeClass('woocommerce-invalid');
        }
    });
});
