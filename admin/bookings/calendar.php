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
                        <i class="fas fa-calendar-day fa-3x mb-3"></i>
                        <p>Click on a date to view bookings</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">

<style>
#calendar {
    background: white;
    border-radius: 8px;
    padding: 10px;
}

.fc-event {
    cursor: pointer;
    font-size: 0.85rem;
    border: none;
    padding: 2px 5px;
    margin-bottom: 2px;
}

.fc-daygrid-day-number {
    padding: 5px;
}

.fc-day-today {
    background-color: #fff3cd !important;
}

.booking-count-badge {
    display: inline-block;
    background: #4CAF50;
    color: white;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 0.75rem;
    font-weight: bold;
    margin-left: 5px;
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
</style>

<?php
$extra_js = '
<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const calendarEl = document.getElementById("calendar");
    const bookingDetailsEl = document.getElementById("booking-details");
    
    // Pre-calculated booking counts for performance
    let bookingCounts = {};
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: "dayGridMonth",
        headerToolbar: {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,dayGridWeek"
        },
        events: function(info, successCallback, failureCallback) {
            // Fetch booking events from API
            fetch("get-calendar-bookings.php?start=" + info.startStr + "&end=" + info.endStr)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Pre-calculate booking counts
                        bookingCounts = {};
                        data.events.forEach(event => {
                            const dateStr = event.start;
                            bookingCounts[dateStr] = (bookingCounts[dateStr] || 0) + 1;
                        });
                        successCallback(data.events);
                    } else {
                        failureCallback(data.message || "Failed to load bookings");
                    }
                })
                .catch(error => {
                    console.error("Error loading bookings:", error);
                    failureCallback(error);
                });
        },
        dateClick: function(info) {
            loadBookingsForDate(info.dateStr);
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            loadBookingsForDate(info.event.startStr);
        },
        dayCellDidMount: function(info) {
            // Add booking count badge to date cells using pre-calculated counts
            const dateStr = info.date.toISOString().split("T")[0];
            const count = bookingCounts[dateStr] || 0;
            
            if (count > 0) {
                const badge = document.createElement("span");
                badge.className = "booking-count-badge";
                badge.textContent = count;
                info.el.querySelector(".fc-daygrid-day-number").appendChild(badge);
            }
        }
    });
    
    calendar.render();
    
    // Function to load bookings for a specific date
    function loadBookingsForDate(date) {
        bookingDetailsEl.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        fetch("get-date-bookings.php?date=" + date)
            .then(response => response.json())
            .then(data => {
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
            .catch(error => {
                console.error("Error:", error);
                bookingDetailsEl.innerHTML = `
                    <div class="alert alert-danger">
                        Error loading bookings. Please try again.
                    </div>
                `;
            });
    }
    
    // Function to display bookings for a date
    function displayBookings(date, bookings) {
        // Parse date without timezone issues by adding midnight time
        const dateObj = new Date(date + "T00:00:00");
        const formattedDate = dateObj.toLocaleDateString("en-US", {
            weekday: "long",
            year: "numeric",
            month: "long",
            day: "numeric"
        });
        
        let html = `
            <div class="date-header">
                <h6 class="mb-1"><i class="fas fa-calendar-day"></i> ${formattedDate}</h6>
                <p class="mb-0 text-muted">
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
            bookings.forEach(booking => {
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
