<?php
$page_title = 'Booking Calendar';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
?>

<!-- Calendar View Card -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <div>
            <h5 class="mb-0"><i class="fas fa-calendar-alt text-primary"></i> Booking Calendar</h5>
            <small class="text-muted">View bookings by date</small>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-list"></i> List View
            </a>
            <a href="add.php" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Add Booking
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <div id="calendar"></div>
            </div>
            <div class="col-md-4">
                <div id="booking-details">
                    <div class="text-center text-muted py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading today's bookings...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#calendar {
    background: white;
    border-radius: 8px;
    padding: 10px;
}

.fc-event {
    cursor: pointer;
    font-size: 0.8rem;
    border: none;
    padding: 1px 4px;
    margin-bottom: 1px;
}

.fc-daygrid-day-number {
    padding: 5px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.fc-day-today {
    background-color: #fff3cd !important;
}

/* Selected day highlight */
.fc-daygrid-day.day-selected {
    box-shadow: inset 0 0 0 2px #0d6efd;
    border-radius: 4px;
}

/* Booking count badge - compact number badge inline next to day number */
.booking-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #198754;
    color: white;
    border-radius: 10px;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1;
    box-shadow: 0 1px 3px rgba(0,0,0,0.25);
    white-space: nowrap;
    cursor: default;
    flex-shrink: 0;
}

/* Day top row: override FullCalendar row-reverse so date number comes first,
   then the event-count badge sits immediately to its right */
.fc-daygrid-day-top {
    display: flex !important;
    flex-direction: row !important;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: nowrap;
    gap: 4px;
    padding: 2px 4px;
}

/* Give cells enough room to display events + badge */
.fc-daygrid-day-frame {
    min-height: 90px !important;
}

/* Custom event content: booking number + customer */
.fc-event-inner {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 1px 3px;
    line-height: 1.3;
}

.fc-event-bnum {
    font-weight: 700;
    font-size: 0.72rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.fc-event-cust {
    font-size: 0.68rem;
    opacity: 0.92;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Heat-map coloring for days with many bookings */
.fc-daygrid-day.has-bookings-1 { background-color: rgba(25, 135, 84, 0.08) !important; }
.fc-daygrid-day.has-bookings-2 { background-color: rgba(25, 135, 84, 0.16) !important; }
.fc-daygrid-day.has-bookings-3 { background-color: rgba(25, 135, 84, 0.24) !important; }
.fc-daygrid-day.has-bookings-many { background-color: rgba(25, 135, 84, 0.32) !important; }
.fc-day-today.has-bookings-1,
.fc-day-today.has-bookings-2,
.fc-day-today.has-bookings-3,
.fc-day-today.has-bookings-many { background-color: rgba(255, 193, 7, 0.4) !important; }

.nepali-date-cell {
    display: block;
    font-size: 0.65rem;
    color: #28a745;
    font-weight: 500;
    margin-top: 2px;
    cursor: default;
}

#booking-details {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    max-height: 600px;
    overflow-y: auto;
}

.booking-detail-card {
    background: white;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-left: 4px solid #4CAF50;
}

.booking-detail-card:hover {
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.booking-detail-card .badge {
    font-size: 0.75rem;
}

.date-header {
    background: white;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Hover tooltip for quick booking preview */
.booking-hover-tooltip {
    position: fixed;
    z-index: 9999;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.18);
    padding: 0;
    min-width: 200px;
    max-width: 280px;
    pointer-events: none;
    font-size: 0.85rem;
}

.bht-header {
    background: #0d6efd;
    color: #fff;
    padding: 7px 12px;
    border-radius: 7px 7px 0 0;
    font-weight: 600;
    font-size: 0.9rem;
}

.bht-body {
    padding: 8px 12px 6px;
}

.bht-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 3px 0;
    border-bottom: 1px solid #f0f0f0;
}

.bht-item:last-child {
    border-bottom: none;
}

.bht-customer {
    font-weight: 500;
    color: #212529;
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.bht-shift {
    font-size: 0.75rem;
    color: #6c757d;
    margin-left: 6px;
    text-transform: capitalize;
}

.bht-more {
    color: #6c757d;
    font-style: italic;
    font-size: 0.8rem;
    padding-top: 4px;
}

.bht-hint {
    color: #0d6efd;
    font-size: 0.78rem;
    text-align: center;
    padding: 5px 0 2px;
    border-top: 1px solid #e9ecef;
    margin-top: 4px;
}
</style>

<?php
$extra_js = '
<!-- FullCalendar JS -->
<script src="' . BASE_URL . '/admin/vendor/fullcalendar/index.global.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const calendarEl = document.getElementById("calendar");
    const bookingDetailsEl = document.getElementById("booking-details");
    
    // Helper function to convert AD date to Nepali date string
    function convertToNepaliDate(dateStr) {
        if (typeof window.nepaliDateUtils === "undefined") {
            return null;
        }
        
        try {
            const dateObj = new Date(dateStr + "T00:00:00");
            if (isNaN(dateObj.getTime())) return null;
            
            const bs = window.nepaliDateUtils.adToBS(
                dateObj.getFullYear(),
                dateObj.getMonth() + 1,
                dateObj.getDate()
            );
            
            if (bs) {
                return window.nepaliDateUtils.formatBSDate(bs.year, bs.month, bs.day);
            }
        } catch (error) {
            console.error("Error converting date:", error);
        }
        return null;
    }
    
    // Helper function to format date as YYYY-MM-DD
    // Accepts Date object or string in YYYY-MM-DD format
    function formatDateStr(date) {
        // If it is already a string in YYYY-MM-DD format, return as-is
        if (typeof date === "string") {
            // Validate format and return
            if (/^\\d{4}-\\d{2}-\\d{2}/.test(date)) {
                return date.substring(0, 10);
            }
            // Try to parse as date
            date = new Date(date);
        }
        
        // Validate it is a Date object
        if (!(date instanceof Date) || isNaN(date.getTime())) {
            console.error("Invalid date provided to formatDateStr:", date);
            return null;
        }
        
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return year + "-" + month + "-" + day;
    }
    
    // Pre-calculated booking counts and event details for tooltip previews
    let bookingCounts = {};
    let allEventsData = {}; // keyed by date -> array of event extendedProps
    let hasLoadedInitialDate = false;
    let selectedDate = null;
    let activeTooltip = null;
    let currentFetchToken = null; // guards against stale out-of-order fetch responses
    
    // --- Hover tooltip functions ---
    function showBookingTooltip(cellEl, dateStr) {
        hideBookingTooltip();
        
        const count = bookingCounts[dateStr] || 0;
        if (count === 0) return;
        
        const events = allEventsData[dateStr] || [];
        
        const tooltip = document.createElement("div");
        tooltip.className = "booking-hover-tooltip";
        
        let bodyHtml = "";
        events.slice(0, 5).forEach(function(e) {
            const customer = e.customer_name || "-";
            const shift = e.shift || "";
            bodyHtml += `<div class="bht-item">
                <span class="bht-customer">${customer}</span>
                <span class="bht-shift">${shift}</span>
            </div>`;
        });
        if (count > 5) {
            bodyHtml += `<div class="bht-more">+${count - 5} more booking${count - 5 !== 1 ? "s" : ""}...</div>`;
        }
        
        tooltip.innerHTML = `
            <div class="bht-header">${count} Booking${count !== 1 ? "s" : ""}</div>
            <div class="bht-body">
                ${bodyHtml}
                <div class="bht-hint"><i class="fas fa-hand-pointer"></i> Click to view details</div>
            </div>
        `;
        
        document.body.appendChild(tooltip);
        
        // Position tooltip near the cell, avoiding viewport edges
        const rect = cellEl.getBoundingClientRect();
        const tw = tooltip.offsetWidth;
        const th = tooltip.offsetHeight;
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        
        let left = rect.right + 6;
        if (left + tw > vw - 8) {
            left = rect.left - tw - 6;
        }
        let top = rect.top;
        if (top + th > vh - 8) {
            top = vh - th - 8;
        }
        if (top < 8) top = 8;
        if (left < 8) left = 8;
        
        tooltip.style.left = left + "px";
        tooltip.style.top = top + "px";
        
        activeTooltip = tooltip;
    }
    
    function hideBookingTooltip() {
        if (activeTooltip) {
            activeTooltip.remove();
            activeTooltip = null;
        }
    }
    
    // --- Badge & heat-map update ---
    function updateDateCellBadges() {
        // Remove existing badges and heat-map classes
        document.querySelectorAll(".booking-count-badge").forEach(function(el) { el.remove(); });
        document.querySelectorAll(".fc-daygrid-day").forEach(function(cell) {
            cell.classList.remove("has-bookings-1", "has-bookings-2", "has-bookings-3", "has-bookings-many");
        });
        
        // Add badges and heat-map classes to each date cell
        document.querySelectorAll(".fc-daygrid-day").forEach(function(cell) {
            const dateStr = cell.getAttribute("data-date");
            if (!dateStr) return;
            
            const count = bookingCounts[dateStr] || 0;
            if (count === 0) return;
            
            // Heat-map background
            if (count === 1) cell.classList.add("has-bookings-1");
            else if (count === 2) cell.classList.add("has-bookings-2");
            else if (count === 3) cell.classList.add("has-bookings-3");
            else cell.classList.add("has-bookings-many");
            
            // Count badge – injected into the day-top row (beside the date number)
            // so it is always visible regardless of cell overflow
            const dayTop = cell.querySelector(".fc-daygrid-day-top");
            if (dayTop) {
                const badge = document.createElement("span");
                badge.className = "booking-count-badge";
                badge.title = count + " booking" + (count !== 1 ? "s" : "") + " on this date";
                badge.textContent = count;
                dayTop.appendChild(badge);
            }
        });
        
        // Re-apply selected date highlight
        if (selectedDate) {
            const sel = document.querySelector(`.fc-daygrid-day[data-date="${selectedDate}"]`);
            if (sel) sel.classList.add("day-selected");
        }
    }
    
    // --- FullCalendar setup ---
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: "dayGridMonth",
        headerToolbar: {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,dayGridWeek"
        },
        dayMaxEvents: 3,
        // Render booking number + customer name inside each event bar
        eventContent: function(arg) {
            const props = arg.event.extendedProps;
            const bookingNum = arg.event.title.split(" - ")[0];
            const customer = props.customer_name || "";
            const firstName = customer.split(" ")[0];
            const totalNum = props.grand_total ? Number(props.grand_total) : 0;
            const totalStr = totalNum > 0 ? " (" + totalNum.toLocaleString(undefined, {maximumFractionDigits: 0}) + ")" : "";
            
            const inner = document.createElement("div");
            inner.className = "fc-event-inner";
            
            const numSpan = document.createElement("span");
            numSpan.className = "fc-event-bnum";
            numSpan.textContent = bookingNum;
            
            const custSpan = document.createElement("span");
            custSpan.className = "fc-event-cust";
            custSpan.textContent = firstName + totalStr;
            
            inner.appendChild(numSpan);
            inner.appendChild(custSpan);
            return { domNodes: [inner] };
        },
        // Reset counts and badges when the visible date range changes (e.g. navigating
        // months) so stale data from the previous range is never briefly shown on the
        // new grid.
        datesSet: function() {
            bookingCounts = {};
            allEventsData = {};
            document.querySelectorAll(".booking-count-badge").forEach(function(el) { el.remove(); });
            document.querySelectorAll(".fc-daygrid-day").forEach(function(cell) {
                cell.classList.remove("has-bookings-1", "has-bookings-2", "has-bookings-3", "has-bookings-many");
            });
        },
        events: function(info, successCallback, failureCallback) {
            // Use a request token so that only the most-recently-started fetch
            // updates bookingCounts (guards against slow out-of-order responses).
            const token = {};
            currentFetchToken = token;

            fetch("get-calendar-bookings.php?start=" + info.startStr + "&end=" + info.endStr)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (token !== currentFetchToken) return; // stale response – ignore
                    if (data.success) {
                        // Reset per-date data
                        bookingCounts = {};
                        allEventsData = {};
                        
                        data.events.forEach(function(event) {
                            const dateStr = event.start;
                            bookingCounts[dateStr] = (bookingCounts[dateStr] || 0) + 1;
                            if (!allEventsData[dateStr]) allEventsData[dateStr] = [];
                            allEventsData[dateStr].push(event.extendedProps || {});
                        });
                        successCallback(data.events);
                    } else {
                        failureCallback(data.message || "Failed to load bookings");
                    }
                })
                .catch(function(error) {
                    console.error("Error loading bookings:", error);
                    failureCallback(error);
                });
        },
        eventsSet: function() {
            // Use a double requestAnimationFrame so our badge injection runs
            // after FullCalendar has had two full rendering frames to commit its
            // own DOM updates (event bars, overflow links, etc.).
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    updateDateCellBadges();
                });
            });
            
            // Auto-load today\'s bookings on the very first render
            if (!hasLoadedInitialDate) {
                hasLoadedInitialDate = true;
                const today = formatDateStr(new Date());
                if (today) {
                    loadBookingsForDate(today);
                }
            }
        },
        dateClick: function(info) {
            loadBookingsForDate(info.dateStr);
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            const dateStr = formatDateStr(info.event.start);
            if (dateStr) {
                loadBookingsForDate(dateStr);
            }
        },
        dayCellDidMount: function(info) {
            const dateStr = formatDateStr(info.date);
            const dayNumberEl = info.el.querySelector(".fc-daygrid-day-number");
            
            // Add Nepali date
            const nepaliDate = convertToNepaliDate(dateStr);
            if (nepaliDate && dayNumberEl) {
                const bsParts = nepaliDate.split(" ");
                if (bsParts.length >= 2 && bsParts[1].length >= 3) {
                    const nepaliSpan = document.createElement("div");
                    nepaliSpan.className = "nepali-date-cell";
                    nepaliSpan.textContent = bsParts[0] + " " + bsParts[1].substring(0, 3);
                    nepaliSpan.title = nepaliDate + " (BS)";
                    dayNumberEl.appendChild(nepaliSpan);
                }
            }
            
            // Hover tooltip for quick booking preview
            info.el.addEventListener("mouseenter", function() {
                showBookingTooltip(info.el, dateStr);
            });
            info.el.addEventListener("mouseleave", function() {
                hideBookingTooltip();
            });
        }
    });
    
    calendar.render();
    
    // --- Load & display bookings for a date ---
    function loadBookingsForDate(date) {
        // Update selected date highlight
        if (selectedDate) {
            const prev = document.querySelector(`.fc-daygrid-day[data-date="${selectedDate}"]`);
            if (prev) prev.classList.remove("day-selected");
        }
        selectedDate = date;
        const curr = document.querySelector(`.fc-daygrid-day[data-date="${date}"]`);
        if (curr) curr.classList.add("day-selected");
        
        bookingDetailsEl.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        fetch("get-date-bookings.php?date=" + date)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    displayBookings(date, data.bookings);
                } else {
                    bookingDetailsEl.innerHTML = `
                        <div class="alert alert-warning">
                            ${data.message || "Failed to load bookings"}
                        </div>
                    `;
                }
            })
            .catch(function(error) {
                console.error("Error:", error);
                bookingDetailsEl.innerHTML = `
                    <div class="alert alert-danger">
                        Error loading bookings. Please try again.
                    </div>
                `;
            });
    }
    
    // --- Render bookings list ---
    function displayBookings(date, bookings) {
        const dateObj = new Date(date + "T00:00:00");
        const formattedDate = dateObj.toLocaleDateString("en-US", {
            weekday: "long",
            year: "numeric",
            month: "long",
            day: "numeric"
        });
        
        const nepaliDate = convertToNepaliDate(date);
        const nepaliDateHtml = nepaliDate ? `<div class="text-success small mt-1"><i class="fas fa-calendar"></i> ${nepaliDate} (BS)</div>` : "";
        
        let html = `
            <div class="date-header">
                <h6 class="mb-1"><i class="fas fa-calendar-day"></i> ${formattedDate}</h6>
                ${nepaliDateHtml}
                <p class="mb-0 text-muted mt-2">
                    <strong>${bookings.length}</strong> booking${bookings.length !== 1 ? "s" : ""} on this date
                </p>
            </div>
        `;
        
        if (bookings.length === 0) {
            html += `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x mb-3"></i>
                    <p>No bookings for this date</p>
                </div>
            `;
        } else {
            bookings.forEach(function(booking) {
                const statusColors = {
                    "confirmed": "success",
                    "pending": "warning",
                    "cancelled": "danger",
                    "completed": "primary",
                    "payment_submitted": "info"
                };
                
                const shiftIcons = {
                    "morning": "sun",
                    "afternoon": "cloud-sun",
                    "evening": "moon",
                    "fullday": "clock"
                };
                
                const paymentColors = {
                    "paid": "success",
                    "partial": "warning",
                    "pending": "danger",
                    "cancelled": "secondary"
                };
                
                const statusColor = statusColors[booking.booking_status] || "secondary";
                const shiftIcon = shiftIcons[booking.shift] || "clock";
                const paymentColor = paymentColors[booking.payment_status] || "danger";
                
                html += `
                    <div class="booking-detail-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong class="text-primary">${booking.booking_number}</strong>
                                <span class="badge bg-${statusColor} ms-2">
                                    ${booking.booking_status.replace("_", " ").toUpperCase()}
                                </span>
                            </div>
                            <a href="view.php?id=${booking.id}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                        
                        <div class="mb-2">
                            <i class="fas fa-user text-muted"></i>
                            <strong>${booking.customer_name}</strong>
                        </div>
                        
                        <div class="mb-2">
                            <i class="fas fa-building text-muted"></i>
                            ${booking.venue_name} - ${booking.hall_name}
                        </div>
                        
                        <div class="mb-2">
                            <i class="fas fa-${shiftIcon} text-muted"></i>
                            <span class="badge bg-info">${booking.shift.toUpperCase()}</span>
                            <i class="fas fa-users text-muted ms-2"></i>
                            ${booking.number_of_guests} guests
                        </div>
                        
                        <div class="mb-2">
                            <i class="fas fa-tag text-muted"></i>
                            ${booking.event_type}
                        </div>
                        
                        ${booking.packages && booking.packages.length > 0 ? `
                        <div class="mb-2">
                            <i class="fas fa-utensils text-muted"></i>
                            <small>Menu: ${booking.packages.join(", ")}</small>
                        </div>
                        ` : ""}
                        
                        ${booking.services && booking.services.length > 0 ? `
                        <div class="mb-2">
                            <i class="fas fa-concierge-bell text-muted"></i>
                            <small>Services: ${booking.services.join(", ")}</small>
                        </div>
                        ` : ""}
                        
                        <div class="mt-2 pt-2 border-top">
                            <strong class="text-success">${booking.grand_total_formatted}</strong>
                            <span class="badge bg-${paymentColor} float-end">
                                ${booking.payment_status.toUpperCase()}
                            </span>
                        </div>
                    </div>
                `;
            });
        }
        
        bookingDetailsEl.innerHTML = html;
    }
});
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>
