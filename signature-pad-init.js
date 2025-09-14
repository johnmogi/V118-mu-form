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
        
        // Set canvas dimensions properly - force immediate sizing
        function resizeCanvas() {
            // Force canvas and container to be visible first
            canvas.style.display = 'block';
            canvas.style.position = 'relative';
            canvas.style.border = '1px solid #ccc';
            canvas.style.backgroundColor = 'white';
            
            // Force container visibility
            const container = canvas.parentElement;
            if (container) {
                container.style.display = 'block';
                container.style.width = '100%';
                container.style.minWidth = '400px';
                container.style.height = 'auto';
            }
            
            // Force fixed dimensions regardless of container
            const width = 400;
            const height = 150;
            
            // Force canvas dimensions multiple ways
            canvas.width = width;
            canvas.height = height;
            canvas.setAttribute('width', width.toString());
            canvas.setAttribute('height', height.toString());
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            canvas.style.minWidth = width + 'px';
            canvas.style.minHeight = height + 'px';
            
            console.log('Canvas forced to dimensions:', width + 'x' + height);
            console.log('Canvas actual attributes:', canvas.getAttribute('width'), canvas.getAttribute('height'));
            console.log('Canvas style dimensions:', canvas.style.width, canvas.style.height);
        }
        
        // Force resize multiple times
        resizeCanvas();
        setTimeout(resizeCanvas, 50);
        setTimeout(resizeCanvas, 200);
        
        // Initialize SignaturePad after ensuring canvas has dimensions
        setTimeout(function() {
            try {
                // Force one more resize before SignaturePad initialization
                resizeCanvas();
                
                signaturePad = new SignaturePad(canvas, {
                    backgroundColor: 'rgba(255, 255, 255, 1)',
                    penColor: 'rgba(0, 0, 0, 1)',
                    minWidth: 1,
                    maxWidth: 2.5,
                    velocityFilterWeight: 0.7
                });
            
            // Make signaturePad globally accessible for debugging
            window.signaturePad = signaturePad;
            
            // Handle signature events
            signaturePad.addEventListener('beginStroke', function() {
                $('#signature_placeholder').hide();
                console.log('Signature stroke started');
            });
            
            signaturePad.addEventListener('endStroke', function() {
                if (!signaturePad.isEmpty()) {
                    const dataURL = signaturePad.toDataURL('image/png', 0.8);
                    hiddenInput.value = dataURL;
                    console.log('Signature saved to hidden input, length:', dataURL.length);
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
                console.log('Signature cleared');
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
        }, 300);
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
        if (step === 4) {
            setTimeout(function() {
                if (!isInitialized) {
                    initializeSignaturePad();
                }
            }, 200);
        }
    });
    
    // Also listen for other step change events
    $(document).on('stepChanged', function(e, stepNumber) {
        if (stepNumber === 4) {
            setTimeout(function() {
                if (!isInitialized) {
                    initializeSignaturePad();
                }
            }, 200);
        }
    });
});
