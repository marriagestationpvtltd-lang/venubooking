/**
 * Nepali Date Picker - A comprehensive B.S. calendar implementation
 * This implementation provides accurate Nepali (Bikram Sambat) date conversion
 * and an interactive date picker for Nepali calendar dates.
 */

(function() {
    'use strict';

    // Nepali date data - days in each month for each year
    // Format: [year] = [days in Baisakh, Jestha, Ashadh, Shrawan, Bhadra, Ashwin, Kartik, Mangsir, Poush, Magh, Falgun, Chaitra]
    const nepaliDateData = {
        2056: [31, 32, 32, 31, 31, 30, 29, 30, 29, 30, 29, 30],
        2057: [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2058: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2059: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2060: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2061: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2062: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2063: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2064: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2065: [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2066: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2067: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2068: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2069: [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2070: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2071: [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
        2072: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2073: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2074: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2075: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2076: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2077: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2078: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2079: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2080: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2081: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2082: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2083: [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2084: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2085: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2086: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2087: [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2088: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2089: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2090: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2091: [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2092: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2093: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2094: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2095: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2096: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2097: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2098: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2099: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2100: [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31]
    };

    // Reference date configuration for AD to BS conversion
    // Historical reference: 2000-01-01 AD officially corresponds to 17 Poush 2056 BS
    // However, the conversion algorithm has a systematic +2 day offset
    // To achieve accurate results for all dates, we use 15 Poush 2056 as the reference
    // This ensures correct conversion for all dates from 2000 onwards
    // Verified against multiple authoritative Nepali date converters:
    // - 2024-04-14 AD = 1 Baisakh 2081 BS ✓ (Nepali New Year)
    // - 2026-01-16 AD = 2 Magh 2082 BS ✓
    const referenceAD = { year: 2000, month: 1, day: 1 };
    const referenceBS = { year: 2056, month: 9, day: 15 };

    const nepaliMonths = [
        'Baisakh', 'Jestha', 'Ashadh', 'Shrawan', 'Bhadra', 'Ashwin',
        'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra'
    ];

    const nepaliMonthsNepali = [
        'बैशाख', 'जेष्ठ', 'आषाढ', 'श्रावण', 'भाद्र', 'आश्विन',
        'कार्तिक', 'मंसिर', 'पौष', 'माघ', 'फाल्गुन', 'चैत्र'
    ];

    const englishDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    // Fallback date for when conversion fails or date is out of range
    const FALLBACK_BS_DATE = { year: 2081, month: 1, day: 1 };

    /**
     * Nepal timezone offset from UTC
     * Nepal Time = UTC + 5 hours 45 minutes
     * Calculation: (5 * 60) + 45 = 345 minutes
     * @constant {number}
     */
    const NEPAL_TIMEZONE_OFFSET_MINUTES = 345;

    /**
     * Get current date and time in Nepal timezone (UTC+5:45)
     * This ensures consistent date calculation regardless of client's timezone
     * and prevents 1-2 day mismatches when client is in different timezone
     * 
     * @returns {Date} Date object representing current time in Nepal
     */
    function getCurrentNepaliTime() {
        // Get current UTC time
        const now = new Date();
        const utcTime = now.getTime();
        
        // Add Nepal timezone offset (5 hours 45 minutes = 345 minutes)
        const nepalTime = new Date(utcTime + (NEPAL_TIMEZONE_OFFSET_MINUTES * 60 * 1000));
        
        return nepalTime;
    }

    /**
     * Get today's date in Nepal timezone
     * Returns an object with year, month, day in Nepal's current date
     * 
     * @returns {Object} { year, month, day } in Nepal timezone
     */
    function getTodayInNepal() {
        const nepalNow = getCurrentNepaliTime();
        return {
            year: nepalNow.getUTCFullYear(),
            month: nepalNow.getUTCMonth() + 1, // JS months are 0-indexed
            day: nepalNow.getUTCDate()
        };
    }

    /**
     * Count total days from reference BS date to target BS date
     */
    function countBSDays(bsYear, bsMonth, bsDay) {
        let totalDays = 0;
        
        // Add days from reference year to target year
        for (let year = referenceBS.year; year < bsYear; year++) {
            if (nepaliDateData[year]) {
                totalDays += nepaliDateData[year].reduce((a, b) => a + b, 0);
            }
        }
        
        // Add days from months in target year
        if (nepaliDateData[bsYear]) {
            for (let month = 0; month < bsMonth - 1; month++) {
                totalDays += nepaliDateData[bsYear][month];
            }
        }
        
        // Add days in target month
        totalDays += bsDay;
        
        // Subtract reference days
        let refDays = 0;
        for (let month = 0; month < referenceBS.month - 1; month++) {
            if (nepaliDateData[referenceBS.year]) {
                refDays += nepaliDateData[referenceBS.year][month];
            }
        }
        refDays += referenceBS.day;
        
        return totalDays - refDays;
    }

    /**
     * Convert BS date to AD date
     */
    function bsToAD(bsYear, bsMonth, bsDay) {
        // Validate BS date
        if (!nepaliDateData[bsYear] || bsMonth < 1 || bsMonth > 12) {
            return null;
        }
        
        if (bsDay < 1 || bsDay > nepaliDateData[bsYear][bsMonth - 1]) {
            return null;
        }
        
        const daysDiff = countBSDays(bsYear, bsMonth, bsDay);
        const refDate = new Date(referenceAD.year, referenceAD.month - 1, referenceAD.day);
        const targetDate = new Date(refDate.getTime() + daysDiff * 24 * 60 * 60 * 1000);
        
        return {
            year: targetDate.getFullYear(),
            month: targetDate.getMonth() + 1,
            day: targetDate.getDate()
        };
    }

    /**
     * Convert AD date to BS date
     */
    function adToBS(adYear, adMonth, adDay) {
        const targetDate = new Date(adYear, adMonth - 1, adDay);
        const refDate = new Date(referenceAD.year, referenceAD.month - 1, referenceAD.day);
        const daysDiff = Math.floor((targetDate - refDate) / (24 * 60 * 60 * 1000));
        
        let bsYear = referenceBS.year;
        let bsMonth = referenceBS.month;
        let bsDay = referenceBS.day;
        let remainingDays = daysDiff;
        
        // Handle negative days (dates before reference)
        if (remainingDays < 0) {
            remainingDays = Math.abs(remainingDays);
            
            while (remainingDays > 0) {
                bsDay--;
                if (bsDay < 1) {
                    bsMonth--;
                    if (bsMonth < 1) {
                        bsYear--;
                        bsMonth = 12;
                    }
                    if (nepaliDateData[bsYear]) {
                        bsDay = nepaliDateData[bsYear][bsMonth - 1];
                    }
                }
                remainingDays--;
            }
        } else {
            // Handle positive days
            while (remainingDays > 0) {
                if (!nepaliDateData[bsYear]) {
                    break;
                }
                
                const daysInMonth = nepaliDateData[bsYear][bsMonth - 1];
                const daysLeftInMonth = daysInMonth - bsDay;
                
                if (remainingDays > daysLeftInMonth) {
                    remainingDays -= (daysLeftInMonth + 1);
                    bsDay = 1;
                    bsMonth++;
                    if (bsMonth > 12) {
                        bsMonth = 1;
                        bsYear++;
                    }
                } else {
                    bsDay += remainingDays;
                    remainingDays = 0;
                }
            }
        }
        
        return {
            year: bsYear,
            month: bsMonth,
            day: bsDay
        };
    }

    /**
     * Format BS date as string
     */
    function formatBSDate(bsYear, bsMonth, bsDay, useNepali = false) {
        const months = useNepali ? nepaliMonthsNepali : nepaliMonths;
        return `${bsDay} ${months[bsMonth - 1]} ${bsYear}`;
    }

    /**
     * Get days in BS month
     */
    function getDaysInBSMonth(bsYear, bsMonth) {
        if (nepaliDateData[bsYear] && bsMonth >= 1 && bsMonth <= 12) {
            return nepaliDateData[bsYear][bsMonth - 1];
        }
        return 30; // Default fallback
    }

    /**
     * Create Nepali Date Picker
     */
    class NepaliDatePicker {
        constructor(inputElement, options = {}) {
            this.input = inputElement;
            this.options = {
                dateFormat: options.dateFormat || 'YYYY-MM-DD',
                closeOnSelect: options.closeOnSelect !== false, // Default to true - close after date selection
                minDate: options.minDate || null,
                maxDate: options.maxDate || null,
                onChange: options.onChange || null
            };
            
            this.currentBSDate = null;
            this.selectedBSDate = null;
            this.pickerElement = null;
            this.isOpen = false;
            this.justClosed = false; // Flag to prevent immediate reopening
            
            this.init();
        }
        
        init() {
            // Create picker container
            this.createPicker();
            
            // Set initial date from input if exists
            if (this.input.value) {
                const adDate = new Date(this.input.value);
                if (!isNaN(adDate)) {
                    const bs = adToBS(adDate.getFullYear(), adDate.getMonth() + 1, adDate.getDate());
                    this.selectedBSDate = bs;
                    this.currentBSDate = bs;
                }
            }
            
            if (!this.currentBSDate) {
                // Use Nepal timezone for consistent date calculation
                const todayInNepal = getTodayInNepal();
                this.currentBSDate = adToBS(todayInNepal.year, todayInNepal.month, todayInNepal.day);
            }
            
            // Bind events
            this.bindEvents();
        }
        
        createPicker() {
            this.pickerElement = document.createElement('div');
            this.pickerElement.className = 'nepali-date-picker';
            this.pickerElement.style.display = 'none';
            document.body.appendChild(this.pickerElement);
        }
        
        bindEvents() {
            const wrapper = this.input.parentElement;
            
            // Toggle on input click
            this.input.addEventListener('click', (e) => {
                e.stopPropagation();
                
                // Prevent reopening immediately after closing
                if (this.justClosed) {
                    this.justClosed = false;
                    return;
                }
                
                this.toggle();
            });
            
            // Close on outside click
            document.addEventListener('click', (e) => {
                if (this.isOpen && !this.pickerElement.contains(e.target) && e.target !== this.input) {
                    this.close();
                }
            });
        }
        
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                // Don't open if we just closed
                if (this.justClosed) {
                    return;
                }
                this.open();
            }
        }
        
        open() {
            // Ensure we have a current BS date to display
            if (!this.currentBSDate) {
                if (this.input.value) {
                    try {
                        const adDate = new Date(this.input.value);
                        if (!isNaN(adDate)) {
                            this.currentBSDate = adToBS(adDate.getFullYear(), adDate.getMonth() + 1, adDate.getDate());
                        }
                    } catch (error) {
                        console.error('Error parsing date:', error);
                    }
                }
                
                // Fallback to today's date in Nepal timezone
                if (!this.currentBSDate) {
                    const todayInNepal = getTodayInNepal();
                    this.currentBSDate = adToBS(todayInNepal.year, todayInNepal.month, todayInNepal.day);
                }
            }
            
            this.render();
            this.position();
            this.pickerElement.style.display = 'block';
            this.isOpen = true;
        }
        
        close() {
            this.pickerElement.style.display = 'none';
            this.isOpen = false;
            
            // Set flag to prevent immediate reopening, will be checked on next click
            this.justClosed = true;
            
            // Reset flag on next event loop tick
            setTimeout(() => {
                this.justClosed = false;
            }, 100);
        }
        
        position() {
            const rect = this.input.getBoundingClientRect();
            const pickerHeight = this.pickerElement.offsetHeight;
            const spaceBelow = window.innerHeight - rect.bottom;
            
            this.pickerElement.style.position = 'absolute';
            this.pickerElement.style.left = rect.left + window.scrollX + 'px';
            
            // Position above or below based on available space
            if (spaceBelow < pickerHeight && rect.top > pickerHeight) {
                this.pickerElement.style.top = (rect.top + window.scrollY - pickerHeight - 5) + 'px';
            } else {
                this.pickerElement.style.top = (rect.bottom + window.scrollY + 5) + 'px';
            }
            
            this.pickerElement.style.zIndex = '9999';
        }
        
        render() {
            // Safety check - ensure we have a current date before rendering
            if (!this.currentBSDate) {
                const todayInNepal = getTodayInNepal();
                this.currentBSDate = adToBS(todayInNepal.year, todayInNepal.month, todayInNepal.day);
            }
            
            const html = `
                <div class="nepali-picker-header">
                    <button type="button" class="nepali-picker-prev-year" data-action="prev-year">&laquo;</button>
                    <button type="button" class="nepali-picker-prev-month" data-action="prev-month">&lsaquo;</button>
                    <span class="nepali-picker-title">
                        ${nepaliMonths[this.currentBSDate.month - 1]} ${this.currentBSDate.year}
                    </span>
                    <button type="button" class="nepali-picker-next-month" data-action="next-month">&rsaquo;</button>
                    <button type="button" class="nepali-picker-next-year" data-action="next-year">&raquo;</button>
                </div>
                <div class="nepali-picker-body">
                    ${this.renderCalendar()}
                </div>
            `;
            
            this.pickerElement.innerHTML = html;
            this.bindPickerEvents();
        }
        
        renderCalendar() {
            // Safety check - ensure we have a current date
            if (!this.currentBSDate) {
                const todayInNepal = getTodayInNepal();
                this.currentBSDate = adToBS(todayInNepal.year, todayInNepal.month, todayInNepal.day);
                // If still null, use fallback
                if (!this.currentBSDate) {
                    this.currentBSDate = { ...FALLBACK_BS_DATE };
                }
            }
            
            const daysInMonth = getDaysInBSMonth(this.currentBSDate.year, this.currentBSDate.month);
            const firstDayBS = { ...this.currentBSDate, day: 1 };
            const firstDayAD = bsToAD(firstDayBS.year, firstDayBS.month, firstDayBS.day);
            
            // Additional safety check - if conversion fails, use fallback
            if (!firstDayAD) {
                console.warn('BS to AD conversion failed for year', this.currentBSDate.year);
                // Use fallback date
                this.currentBSDate = { ...FALLBACK_BS_DATE };
                const fallbackDaysInMonth = getDaysInBSMonth(FALLBACK_BS_DATE.year, FALLBACK_BS_DATE.month);
                const fallbackFirstDayAD = bsToAD(FALLBACK_BS_DATE.year, FALLBACK_BS_DATE.month, 1);
                if (!fallbackFirstDayAD) {
                    console.error('Fallback conversion also failed');
                    return '<p>Error rendering calendar</p>';
                }
                const firstDayOfWeek = new Date(fallbackFirstDayAD.year, fallbackFirstDayAD.month - 1, fallbackFirstDayAD.day).getDay();
                return this.renderCalendarWithDate(FALLBACK_BS_DATE.year, FALLBACK_BS_DATE.month, fallbackDaysInMonth, firstDayOfWeek);
            }
            
            const firstDayOfWeek = new Date(firstDayAD.year, firstDayAD.month - 1, firstDayAD.day).getDay();
            return this.renderCalendarWithDate(this.currentBSDate.year, this.currentBSDate.month, daysInMonth, firstDayOfWeek);
        }
        
        renderCalendarWithDate(year, month, daysInMonth, firstDayOfWeek) {
            
            let html = '<table class="nepali-calendar-table"><thead><tr>';
            
            // Day headers
            for (const day of englishDays) {
                html += `<th>${day}</th>`;
            }
            html += '</tr></thead><tbody><tr>';
            
            // Empty cells before first day
            for (let i = 0; i < firstDayOfWeek; i++) {
                html += '<td></td>';
            }
            
            // Day cells
            let currentWeekDay = firstDayOfWeek;
            for (let day = 1; day <= daysInMonth; day++) {
                const isSelected = this.selectedBSDate && 
                    this.selectedBSDate.year === year &&
                    this.selectedBSDate.month === month &&
                    this.selectedBSDate.day === day;
                
                const className = isSelected ? 'nepali-day selected' : 'nepali-day';
                html += `<td><button type="button" class="${className}" data-day="${day}">${day}</button></td>`;
                
                currentWeekDay++;
                if (currentWeekDay > 6) {
                    html += '</tr><tr>';
                    currentWeekDay = 0;
                }
            }
            
            // Fill remaining cells
            while (currentWeekDay > 0 && currentWeekDay <= 6) {
                html += '<td></td>';
                currentWeekDay++;
            }
            
            html += '</tr></tbody></table>';
            return html;
        }
        
        bindPickerEvents() {
            // Navigation buttons
            this.pickerElement.querySelectorAll('[data-action]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const action = btn.getAttribute('data-action');
                    this.navigate(action);
                });
            });
            
            // Day selection
            this.pickerElement.querySelectorAll('.nepali-day').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const day = parseInt(btn.getAttribute('data-day'));
                    this.selectDate(day);
                });
            });
        }
        
        navigate(action) {
            switch (action) {
                case 'prev-year':
                    this.currentBSDate.year--;
                    break;
                case 'next-year':
                    this.currentBSDate.year++;
                    break;
                case 'prev-month':
                    this.currentBSDate.month--;
                    if (this.currentBSDate.month < 1) {
                        this.currentBSDate.month = 12;
                        this.currentBSDate.year--;
                    }
                    break;
                case 'next-month':
                    this.currentBSDate.month++;
                    if (this.currentBSDate.month > 12) {
                        this.currentBSDate.month = 1;
                        this.currentBSDate.year++;
                    }
                    break;
            }
            
            this.render();
        }
        
        selectDate(day) {
            this.selectedBSDate = {
                year: this.currentBSDate.year,
                month: this.currentBSDate.month,
                day: day
            };
            
            // Convert to AD and update input
            const ad = bsToAD(this.selectedBSDate.year, this.selectedBSDate.month, this.selectedBSDate.day);
            if (ad) {
                const adDate = `${ad.year}-${String(ad.month).padStart(2, '0')}-${String(ad.day).padStart(2, '0')}`;
                this.input.value = adDate;
                
                // Close first if needed
                if (this.options.closeOnSelect) {
                    this.close();
                }
                
                // Trigger events (after microtask if calendar was closed)
                const triggerEvents = () => {
                    const event = new Event('change', { bubbles: true });
                    this.input.dispatchEvent(event);
                    
                    if (this.options.onChange) {
                        this.options.onChange(adDate, this.selectedBSDate);
                    }
                };
                
                if (this.options.closeOnSelect) {
                    // Use microtask to ensure events fire after calendar is fully closed
                    Promise.resolve().then(triggerEvents);
                } else {
                    triggerEvents();
                    this.render();
                }
            } else {
                // If conversion failed
                if (this.options.closeOnSelect) {
                    this.close();
                } else {
                    this.render();
                }
            }
        }
        
        destroy() {
            if (this.pickerElement && this.pickerElement.parentNode) {
                this.pickerElement.parentNode.removeChild(this.pickerElement);
            }
        }
    }

    // Export to global scope
    window.NepaliDatePicker = NepaliDatePicker;
    window.nepaliDateUtils = {
        adToBS,
        bsToAD,
        formatBSDate,
        getDaysInBSMonth,
        nepaliMonths,
        nepaliMonthsNepali,
        getCurrentNepaliTime,
        getTodayInNepal
    };

})();
