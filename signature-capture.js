/**
 * Simple Signature Capture System
 * Standalone JavaScript for signature functionality
 */

class SimpleSignatureCapture {
    constructor(canvasId, options = {}) {
        this.canvas = document.getElementById(canvasId);
        this.signaturePad = null;
        this.options = {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)',
            minWidth: 0.5,
            maxWidth: 2.5,
            ...options
        };
        
        this.init();
    }
    
    init() {
        if (!this.canvas) {
            console.error('Signature canvas not found');
            return;
        }
        
        // Initialize SignaturePad
        this.signaturePad = new SignaturePad(this.canvas, this.options);
        
        // Set canvas size
        this.resizeCanvas();
        
        // Add event listeners
        window.addEventListener('resize', () => this.resizeCanvas());
        
        console.log('Signature capture initialized');
    }
    
    resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const rect = this.canvas.getBoundingClientRect();
        
        this.canvas.width = rect.width * ratio;
        this.canvas.height = rect.height * ratio;
        this.canvas.getContext('2d').scale(ratio, ratio);
        
        this.signaturePad.clear();
    }
    
    clear() {
        if (this.signaturePad) {
            this.signaturePad.clear();
        }
    }
    
    isEmpty() {
        return this.signaturePad ? this.signaturePad.isEmpty() : true;
    }
    
    getSignatureData() {
        if (this.signaturePad && !this.signaturePad.isEmpty()) {
            return this.signaturePad.toDataURL('image/png', 0.8);
        }
        return null;
    }
    
    saveSignature(submissionId = 0, userEmail = '') {
        const signatureData = this.getSignatureData();
        
        if (!signatureData) {
            alert('אנא חתום במסגרת לפני השמירה');
            return Promise.reject('No signature data');
        }
        
        const formData = new FormData();
        formData.append('action', 'save_signature');
        formData.append('nonce', signature_ajax.nonce);
        formData.append('signature_data', signatureData);
        formData.append('submission_id', submissionId);
        formData.append('user_email', userEmail);
        
        return fetch(signature_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Signature saved successfully:', data.data);
                return data.data;
            } else {
                console.error('Signature save failed:', data.data);
                throw new Error(data.data);
            }
        })
        .catch(error => {
            console.error('Signature save error:', error);
            throw error;
        });
    }
}

// Global signature instance
let globalSignature = null;

// Initialize signature when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Look for signature canvas
    const signatureCanvas = document.getElementById('signature_canvas');
    if (signatureCanvas) {
        globalSignature = new SimpleSignatureCapture('signature_canvas');
        
        // Set up clear button
        const clearButton = document.getElementById('clear_signature');
        if (clearButton) {
            clearButton.addEventListener('click', function() {
                globalSignature.clear();
                updateSignatureStatus('חתימה נדרשת', 'signature-required');
            });
        }
        
        // Monitor signature changes
        if (globalSignature.signaturePad) {
            globalSignature.signaturePad.addEventListener('endStroke', function() {
                if (!globalSignature.isEmpty()) {
                    updateSignatureStatus('✓ חתימה נשמרה', 'signature-valid');
                    
                    // Update hidden input if exists
                    const hiddenInput = document.getElementById('signature_data');
                    if (hiddenInput) {
                        hiddenInput.value = globalSignature.getSignatureData();
                    }
                } else {
                    updateSignatureStatus('חתימה נדרשת', 'signature-required');
                }
            });
        }
    }
});

// Helper function to update signature status
function updateSignatureStatus(message, className) {
    const statusElement = document.getElementById('signature_status');
    if (statusElement) {
        statusElement.textContent = message;
        statusElement.className = 'signature-status ' + className;
    }
}

// Test signature save function
function testSignatureSave() {
    if (globalSignature) {
        globalSignature.saveSignature(999, 'test@example.com')
            .then(result => {
                alert('חתימה נשמרה בהצלחה! ID: ' + result.signature_id);
            })
            .catch(error => {
                alert('שגיאה בשמירת החתימה: ' + error);
            });
    } else {
        alert('מערכת החתימה לא מוכנה');
    }
}
