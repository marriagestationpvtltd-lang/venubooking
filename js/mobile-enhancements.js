/**
 * Mobile Enhancements for Booking Flow
 * Handles mobile-specific UX improvements including:
 * - Collapsible booking summary
 * - Fixed bottom navigation
 * - Smooth scroll to errors
 * - Collapsible sections
 */

(function() {
    'use strict';

    // Constants
    const MOBILE_BREAKPOINT = 767;
    const MAX_MENU_HEIGHT = 300;
    const COLLAPSED_HEIGHT = 200;
    const SCROLL_OFFSET = 100;

    // Check if we're on mobile
    function isMobile() {
        return window.innerWidth <= MOBILE_BREAKPOINT;
    }

    // Initialize mobile enhancements on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        if (isMobile()) {
            initMobileBookingSummary();
            initMobileBottomNav();
            initCollapsibleCards();
        }

        // Re-initialize on window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (isMobile()) {
                    initMobileBookingSummary();
                    initMobileBottomNav();
                } else {
                    removeMobileEnhancements();
                }
            }, 250);
        });
    });

    /**
     * Make booking summary bar collapsible on mobile
     */
    function initMobileBookingSummary() {
        const summaryBar = document.querySelector('.booking-summary-bar');
        if (!summaryBar || summaryBar.dataset.mobileEnhanced) return;

        summaryBar.dataset.mobileEnhanced = 'true';

        // Make it clickable
        summaryBar.style.cursor = 'pointer';
        
        // Add click handler to toggle
        summaryBar.addEventListener('click', function(e) {
            // Don't toggle if clicking on a button or link inside
            if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A') return;
            
            this.classList.toggle('expanded');
        });

        // Start collapsed
        summaryBar.classList.remove('expanded');
    }

    /**
     * Move navigation buttons to fixed bottom bar on mobile
     */
    function initMobileBottomNav() {
        // Check if we're on a booking step page
        const desktopNavButtons = document.querySelector('.row.mt-4');
        if (!desktopNavButtons || desktopNavButtons.dataset.mobileEnhanced) return;

        const backButton = desktopNavButtons.querySelector('.btn-outline-secondary');
        const continueButton = desktopNavButtons.querySelector('.btn-success');
        
        // Filter for submit buttons
        let submitButton = null;
        if (continueButton && continueButton.type === 'submit') {
            submitButton = continueButton;
        }
        
        if (!backButton && !submitButton && !continueButton) return;

        desktopNavButtons.dataset.mobileEnhanced = 'true';

        // Hide desktop nav on mobile
        desktopNavButtons.classList.add('desktop-nav-buttons');

        // Create mobile bottom nav if it doesn't exist
        let mobileNav = document.querySelector('.mobile-bottom-nav');
        if (!mobileNav) {
            mobileNav = document.createElement('div');
            mobileNav.className = 'mobile-bottom-nav';
            document.body.appendChild(mobileNav);
        }

        // Clear existing content
        mobileNav.innerHTML = '';

        // Clone and add back button
        if (backButton) {
            const mobileBack = backButton.cloneNode(true);
            mobileBack.className = 'btn btn-outline-secondary';
            mobileNav.appendChild(mobileBack);
        }

        // Clone and add continue button
        if (continueButton) {
            const mobileContinue = continueButton.cloneNode(true);
            mobileContinue.className = 'btn btn-success';
            
            // If it's a submit button, make sure it submits the correct form
            if (submitButton && continueButton === submitButton) {
                const form = continueButton.closest('form');
                if (form) {
                    mobileContinue.addEventListener('click', function(e) {
                        e.preventDefault();
                        form.submit();
                    });
                }
            }
            
            mobileNav.appendChild(mobileContinue);
        }
    }

    /**
     * Initialize collapsible cards for better mobile UX
     */
    function initCollapsibleCards() {
        // Add collapsible functionality to long content sections
        const menuCards = document.querySelectorAll('.menu-card');
        const serviceCards = document.querySelectorAll('.service-card');

        // Make menu items collapsible if they're too long
        menuCards.forEach(function(card) {
            const menuItems = card.querySelector('.menu-items');
            if (menuItems && menuItems.scrollHeight > MAX_MENU_HEIGHT) {
                const wrapper = document.createElement('div');
                wrapper.className = 'collapsible-wrapper collapsed';
                wrapper.style.maxHeight = COLLAPSED_HEIGHT + 'px';
                wrapper.style.overflow = 'hidden';
                wrapper.style.position = 'relative';
                
                menuItems.parentNode.insertBefore(wrapper, menuItems);
                wrapper.appendChild(menuItems);

                const toggleBtn = document.createElement('button');
                toggleBtn.className = 'btn btn-sm btn-link';
                toggleBtn.textContent = 'Show more';
                toggleBtn.type = 'button';
                toggleBtn.style.width = '100%';
                
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (wrapper.classList.contains('collapsed')) {
                        wrapper.style.maxHeight = 'none';
                        wrapper.classList.remove('collapsed');
                        toggleBtn.textContent = 'Show less';
                    } else {
                        wrapper.style.maxHeight = COLLAPSED_HEIGHT + 'px';
                        wrapper.classList.add('collapsed');
                        toggleBtn.textContent = 'Show more';
                    }
                });

                wrapper.parentNode.insertBefore(toggleBtn, wrapper.nextSibling);
            }
        });
    }

    /**
     * Remove mobile enhancements when switching to desktop view
     */
    function removeMobileEnhancements() {
        const mobileNav = document.querySelector('.mobile-bottom-nav');
        if (mobileNav) {
            mobileNav.remove();
        }

        const desktopNavButtons = document.querySelector('.desktop-nav-buttons');
        if (desktopNavButtons) {
            desktopNavButtons.classList.remove('desktop-nav-buttons');
        }
    }

    /**
     * Smooth scroll to first validation error
     */
    function scrollToError(errorElement) {
        if (errorElement) {
            const elementPosition = errorElement.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - SCROLL_OFFSET;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });

            // Focus the element
            errorElement.focus();
        }
    }

    // Expose scrollToError globally for form validation handlers
    window.scrollToError = scrollToError;

    // Cache for invalid element query to avoid repeated DOM searches
    let invalidCheckScheduled = false;

    // Handle form validation errors
    document.addEventListener('invalid', function(e) {
        e.preventDefault();
        
        // Use requestAnimationFrame to batch multiple invalid events
        if (!invalidCheckScheduled) {
            invalidCheckScheduled = true;
            requestAnimationFrame(function() {
                const firstInvalid = document.querySelector('.is-invalid, :invalid');
                if (firstInvalid) {
                    scrollToError(firstInvalid);
                }
                invalidCheckScheduled = false;
            });
        }
    }, true);

})();
