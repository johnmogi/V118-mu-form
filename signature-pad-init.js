// Signature pad initialization and management
jQuery(document).ready(function($) {
    let signaturePad = null;
    let isInitialized = false;
    
    function initializeSignaturePad() {
        if (isInitialized || !window.SignaturePad) {
            return;
        }
        
        const canvas = document.getElementById('signature_pad');
        const clearButton = document.getElementById('clear_signature');
        const hiddenInput = document.getElementById('signature_data');
        
        if (!canvas || !clearButton || !hiddenInput) {
            console.warn('Signature elements not found, retrying...');
            setTimeout(initializeSignaturePad, 500);
            return;
        }
        
        // Set canvas dimensions
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
        }
        
        resizeCanvas();
        
        // Initialize SignaturePad
        try {
            signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 1)',
                penColor: 'rgba(0, 0, 0, 1)',
                minWidth: 1,
                maxWidth: 2.5,
                velocityFilterWeight: 0.7
            });
            
            // Handle signature events
            signaturePad.addEventListener('beginStroke', function() {
                $('#signature_placeholder').hide();
            });
            
            signaturePad.addEventListener('endStroke', function() {
                if (!signaturePad.isEmpty()) {
                    const dataURL = signaturePad.toDataURL('image/png', 0.8);
                    hiddenInput.value = dataURL;
                    $('#signature_status').text('✓ חתימה נשמרה').removeClass('signature-required').addClass('signature-valid');
                }
            });
            
            // Clear button functionality
            clearButton.addEventListener('click', function(e) {
                e.preventDefault();
                signaturePad.clear();
                hiddenInput.value = '';
                $('#signature_placeholder').show();
                $('#signature_status').text('חתימה נדרשת').removeClass('signature-valid').addClass('signature-required');
            });
            
            // Handle window resize
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    const data = signaturePad.toData();
                    resizeCanvas();
                    signaturePad.fromData(data);
                }, 250);
            });
            
            isInitialized = true;
            console.log('Signature pad initialized successfully');
            
        } catch (error) {
            console.error('Error initializing signature pad:', error);
            setTimeout(initializeSignaturePad, 1000);
        }
    }
    
    // Initialize when SignaturePad library is loaded
    if (window.SignaturePad) {
        initializeSignaturePad();
    } else {
        // Wait for SignaturePad library to load
        let checkCount = 0;
        const checkInterval = setInterval(function() {
            if (window.SignaturePad || checkCount > 50) {
                clearInterval(checkInterval);
                if (window.SignaturePad) {
                    initializeSignaturePad();
                } else {
                    console.error('SignaturePad library failed to load');
                }
            }
            checkCount++;
        }, 100);
    }
    
    // Re-initialize when step changes to step 4
    $(document).on('formStepChanged', function(e, step) {
        if (step === 4 && !isInitialized) {
            setTimeout(initializeSignaturePad, 100);
        }
    });
});
