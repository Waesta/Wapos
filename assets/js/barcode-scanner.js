/**
 * Barcode Scanner Module
 * 
 * Supports:
 * - USB/Bluetooth barcode scanners (keyboard wedge)
 * - Camera-based scanning (using QuaggaJS or ZXing)
 * - Manual entry
 * 
 * Usage:
 * const scanner = new BarcodeScanner({
 *     onScan: (barcode) => { console.log('Scanned:', barcode); },
 *     onError: (error) => { console.error(error); }
 * });
 * scanner.enable();
 */

class BarcodeScanner {
    constructor(options = {}) {
        this.options = {
            onScan: options.onScan || (() => {}),
            onError: options.onError || console.error,
            minLength: options.minLength || 4,
            maxLength: options.maxLength || 50,
            scanTimeout: options.scanTimeout || 100, // ms between keystrokes
            prefix: options.prefix || '', // Expected prefix (e.g., for scanner config)
            suffix: options.suffix || '', // Expected suffix
            preventDefault: options.preventDefault !== false,
            debug: options.debug || false
        };
        
        this.buffer = '';
        this.lastKeyTime = 0;
        this.enabled = false;
        this.cameraEnabled = false;
        this.videoStream = null;
        
        this._handleKeyPress = this._handleKeyPress.bind(this);
    }
    
    /**
     * Enable keyboard wedge scanning (USB/Bluetooth scanners)
     */
    enable() {
        if (this.enabled) return;
        
        document.addEventListener('keypress', this._handleKeyPress);
        this.enabled = true;
        
        if (this.options.debug) {
            console.log('Barcode scanner enabled (keyboard mode)');
        }
    }
    
    /**
     * Disable keyboard wedge scanning
     */
    disable() {
        document.removeEventListener('keypress', this._handleKeyPress);
        this.enabled = false;
        this.buffer = '';
        
        if (this.options.debug) {
            console.log('Barcode scanner disabled');
        }
    }
    
    /**
     * Handle keypress events from barcode scanner
     */
    _handleKeyPress(event) {
        const now = Date.now();
        const char = event.key;
        
        // Check if this is rapid input (typical of barcode scanners)
        if (now - this.lastKeyTime > this.options.scanTimeout) {
            // Too slow, reset buffer
            this.buffer = '';
        }
        
        this.lastKeyTime = now;
        
        // Enter key signals end of barcode
        if (char === 'Enter') {
            if (this.buffer.length >= this.options.minLength) {
                this._processScan(this.buffer);
                
                if (this.options.preventDefault) {
                    event.preventDefault();
                }
            }
            this.buffer = '';
            return;
        }
        
        // Add character to buffer
        if (char.length === 1 && this.buffer.length < this.options.maxLength) {
            this.buffer += char;
            
            // Prevent default if we're in the middle of a scan
            if (this.options.preventDefault && this.buffer.length > 2) {
                event.preventDefault();
            }
        }
    }
    
    /**
     * Process a completed scan
     */
    _processScan(barcode) {
        // Remove prefix/suffix if configured
        if (this.options.prefix && barcode.startsWith(this.options.prefix)) {
            barcode = barcode.substring(this.options.prefix.length);
        }
        if (this.options.suffix && barcode.endsWith(this.options.suffix)) {
            barcode = barcode.substring(0, barcode.length - this.options.suffix.length);
        }
        
        // Validate barcode
        if (barcode.length < this.options.minLength) {
            return;
        }
        
        if (this.options.debug) {
            console.log('Barcode scanned:', barcode);
        }
        
        // Trigger callback
        this.options.onScan(barcode);
    }
    
    /**
     * Manual barcode entry
     */
    manualEntry(barcode) {
        if (barcode && barcode.length >= this.options.minLength) {
            this._processScan(barcode.trim());
        }
    }
    
    /**
     * Enable camera-based scanning
     * Requires QuaggaJS library to be loaded
     */
    async enableCamera(videoElement, options = {}) {
        if (typeof Quagga === 'undefined') {
            this.options.onError('QuaggaJS library not loaded. Include it for camera scanning.');
            return false;
        }
        
        const config = {
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: videoElement,
                constraints: {
                    facingMode: options.facingMode || "environment",
                    width: { min: 640 },
                    height: { min: 480 }
                }
            },
            decoder: {
                readers: options.readers || [
                    "ean_reader",
                    "ean_8_reader",
                    "code_128_reader",
                    "code_39_reader",
                    "upc_reader",
                    "upc_e_reader"
                ]
            },
            locate: true,
            locator: {
                patchSize: "medium",
                halfSample: true
            }
        };
        
        return new Promise((resolve, reject) => {
            Quagga.init(config, (err) => {
                if (err) {
                    this.options.onError(err);
                    reject(err);
                    return;
                }
                
                Quagga.start();
                this.cameraEnabled = true;
                
                Quagga.onDetected((result) => {
                    if (result && result.codeResult) {
                        const barcode = result.codeResult.code;
                        this._processScan(barcode);
                    }
                });
                
                if (this.options.debug) {
                    console.log('Camera barcode scanner enabled');
                }
                
                resolve(true);
            });
        });
    }
    
    /**
     * Disable camera scanning
     */
    disableCamera() {
        if (typeof Quagga !== 'undefined' && this.cameraEnabled) {
            Quagga.stop();
            this.cameraEnabled = false;
            
            if (this.options.debug) {
                console.log('Camera barcode scanner disabled');
            }
        }
    }
    
    /**
     * Check if a string looks like a valid barcode
     */
    static isValidBarcode(code) {
        if (!code || code.length < 4) return false;
        
        // EAN-13
        if (/^\d{13}$/.test(code)) return true;
        
        // EAN-8
        if (/^\d{8}$/.test(code)) return true;
        
        // UPC-A
        if (/^\d{12}$/.test(code)) return true;
        
        // UPC-E
        if (/^\d{6,8}$/.test(code)) return true;
        
        // Code 128 / Code 39 (alphanumeric)
        if (/^[A-Z0-9\-\.\$\/\+\%\s]{4,50}$/i.test(code)) return true;
        
        return false;
    }
    
    /**
     * Calculate EAN-13 check digit
     */
    static calculateEAN13CheckDigit(code) {
        if (code.length !== 12) return null;
        
        let sum = 0;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(code[i]) * (i % 2 === 0 ? 1 : 3);
        }
        
        return (10 - (sum % 10)) % 10;
    }
    
    /**
     * Generate a random barcode (for testing)
     */
    static generateTestBarcode(type = 'ean13') {
        if (type === 'ean13') {
            let code = '';
            for (let i = 0; i < 12; i++) {
                code += Math.floor(Math.random() * 10);
            }
            code += BarcodeScanner.calculateEAN13CheckDigit(code);
            return code;
        }
        
        // Default: random alphanumeric
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let code = '';
        for (let i = 0; i < 10; i++) {
            code += chars[Math.floor(Math.random() * chars.length)];
        }
        return code;
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BarcodeScanner;
}

// Make available globally
window.BarcodeScanner = BarcodeScanner;
