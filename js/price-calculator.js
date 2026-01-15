/**
 * Price Calculator
 */

class PriceCalculator {
    constructor() {
        this.hallPrice = 0;
        this.menuPrices = [];
        this.servicePrices = [];
        this.guests = 0;
        this.taxRate = 13; // Default, will be loaded from settings
        
        // Load tax rate from global settings when available
        this.loadTaxRate();
    }
    
    async loadTaxRate() {
        // Wait for settings to be loaded - max 30 retries (3 seconds)
        let retries = 0;
        const maxRetries = 30;
        
        const checkSettings = () => {
            if (typeof appSettings !== 'undefined' && 'tax_rate' in appSettings && appSettings.tax_rate !== undefined) {
                this.taxRate = appSettings.tax_rate;
            } else if (retries < maxRetries) {
                retries++;
                setTimeout(checkSettings, 100);
            } else {
                console.warn('Failed to load tax rate from settings, using default');
            }
        };
        
        checkSettings();
    }
    
    setHallPrice(price) {
        this.hallPrice = parseFloat(price) || 0;
    }
    
    setGuests(count) {
        this.guests = parseInt(count) || 0;
    }
    
    setTaxRate(rate) {
        this.taxRate = parseFloat(rate) || 0;
    }
    
    addMenu(pricePerPerson) {
        this.menuPrices.push(parseFloat(pricePerPerson) || 0);
    }
    
    removeMenu(index) {
        if (index >= 0 && index < this.menuPrices.length) {
            this.menuPrices.splice(index, 1);
        }
    }
    
    clearMenus() {
        this.menuPrices = [];
    }
    
    addService(price) {
        this.servicePrices.push(parseFloat(price) || 0);
    }
    
    removeService(index) {
        if (index >= 0 && index < this.servicePrices.length) {
            this.servicePrices.splice(index, 1);
        }
    }
    
    clearServices() {
        this.servicePrices = [];
    }
    
    calculateMenuTotal() {
        const totalPerPerson = this.menuPrices.reduce((sum, price) => sum + price, 0);
        return totalPerPerson * this.guests;
    }
    
    calculateServicesTotal() {
        return this.servicePrices.reduce((sum, price) => sum + price, 0);
    }
    
    calculateSubtotal() {
        return this.hallPrice + this.calculateMenuTotal() + this.calculateServicesTotal();
    }
    
    calculateTax() {
        return this.calculateSubtotal() * (this.taxRate / 100);
    }
    
    calculateGrandTotal() {
        return this.calculateSubtotal() + this.calculateTax();
    }
    
    getBreakdown() {
        return {
            hallPrice: this.hallPrice,
            menuTotal: this.calculateMenuTotal(),
            servicesTotal: this.calculateServicesTotal(),
            subtotal: this.calculateSubtotal(),
            taxAmount: this.calculateTax(),
            grandTotal: this.calculateGrandTotal()
        };
    }
}

// Global calculator instance
const priceCalculator = new PriceCalculator();
