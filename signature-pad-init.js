// Signature system functions
var testSignatureSystem = function() {
    console.log("Testing signature system...");
    
    // Check if signature canvas exists
    var canvas = document.getElementById("signature_canvas");
    if (!canvas) {
        alert("❌ Signature canvas not found");
        return;
    }
    
    // Check if SignaturePad is loaded
    if (typeof SignaturePad === 'undefined') {
        alert('❌ SignaturePad library not loaded');
        return;
    }
    
    // Check if signature_ajax is available
    if (typeof signature_ajax === 'undefined') {
        alert('❌ No AJAX endpoint defined for signature saving');
        return;
    }
    
    console.log('✅ Signature system is working correctly');
};

// Initialize the signature pad when the document is ready
jQuery(document).ready(function($) {
    // Initialize the signature pad on step 5
    $(document).on('formStepChanged', function(e, step) {
        if (step === 5) {
            initializeSignaturePad();
        }
    });
    
    // Initialize signature pad
    function initializeSignaturePad() {
        if (isSignaturePadInitialized) {
            return;
        }
        
        const canvas = document.getElementById('signature_pad');
        const placeholder = document.getElementById('signature_placeholder');
        const statusElement = document.getElementById('signature_status');
        const clearButton = document.getElementById('clear_signature');
        const hiddenInput = document.getElementById('signature_data');
        
        if (!canvas || !placeholder || !statusElement || !clearButton || !hiddenInput) {
            console.warn('Signature elements not found, retrying...');
            setTimeout(initializeSignaturePad, 500);
            return;
        }
        
        // Set canvas dimensions properly
        function setupCanvas() {
            const container = canvas.parentElement;
            const containerWidth = container.offsetWidth || 400;
            const canvasHeight = 150;
            
            // Set display size
            canvas.style.width = containerWidth + 'px';
            canvas.style.height = canvasHeight + 'px';
            
            // Set actual size in memory (scaled for high DPI displays)
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = containerWidth * ratio;
            canvas.height = canvasHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            
            return canvas;
        }
        
        // Initialize the signature pad
        const signaturePad = new SignaturePad(setupCanvas(), {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)'
        });
        
        // Update status
        function updateStatus() {
            if (signaturePad.isEmpty()) {
                statusElement.textContent = 'אנא חתום למטה';
                statusElement.className = 'signature-status';
                hiddenInput.value = '';
            } else {
                statusElement.textContent = 'חתימה תקינה';
                statusElement.className = 'signature-status valid';
                hiddenInput.value = signaturePad.toDataURL('image/png');
            }
        }
        
        // Clear the signature
        clearButton.addEventListener('click', function(e) {
            e.preventDefault();
            signaturePad.clear();
            updateStatus();
        });
        
        // Update status when signature changes
        signaturePad.addEventListener('endStroke', updateStatus);
        
        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                const data = signaturePad.toData();
                setupCanvas();
                signaturePad.clear();
                signaturePad.fromData(data);
            }, 250);
        });
        
        // Initial status update
        updateStatus();
        isSignaturePadInitialized = true;
    }
    
    // Initialize on page load if already on step 5
    if ($('.form-step[data-step="5"]').hasClass('active')) {
        initializeSignaturePad();
    }
});
