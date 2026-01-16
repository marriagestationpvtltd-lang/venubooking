# Venue Booking System

A complete party/event booking system built with PHP, MySQL, Bootstrap 5, and jQuery. This system allows users to book venues for various events like weddings, birthday parties, corporate events, and more.

## Features

### User Features
- **Step-by-Step Booking Flow**
  - Select event details (shift, date, guests, event type)
  - Browse available venues and halls
  - Choose menus with detailed items
  - Add additional services (decoration, photography, etc.)
  - Enter customer information
  - View booking confirmation with all details

- **Real-time Price Calculation**
  - Dynamic pricing based on selections
  - Transparent cost breakdown
  - Tax calculation

- **Availability Checking**
  - Prevents double bookings
  - Shows only available venues/halls for selected dates

- **Responsive Design**
  - Mobile-friendly interface
  - Green color scheme for elegant look
  - Clean and intuitive UI

### Admin Features
- **Dashboard**
  - Statistics and analytics
  - Recent bookings overview
  - Upcoming events calendar
  - Revenue metrics

- **Venue Management**
  - Add, edit, delete venues
  - Manage venue details and images

- **Hall Management**
  - Link halls to venues
  - Set capacity and pricing
  - Manage hall images
  - Assign menus to halls

- **Menu Management**
  - Create menus with items
  - Set price per person
  - Manage menu categories

- **Booking Management**
  - View all bookings
  - Update booking status
  - Update payment status
  - Filter and search bookings

- **Customer Management**
  - View customer database
  - Track booking history

- **Services Management**
  - Manage additional services
  - Set service pricing

- **Reports**
  - Monthly revenue reports
  - Booking statistics
  - Visual charts and analytics

- **Settings**
  - Configure system settings
  - Tax rates and currency
  - Contact information

## System Requirements

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- Web browser (Chrome, Firefox, Safari, Edge)

## Installation

### 1. Clone or Download the Repository

```bash
git clone https://github.com/marriagestationpvtltd-lang/venubooking.git
cd venubooking
```

### 2. Database Setup

**Choose the right SQL file for your needs:**

**For Production/Live Deployment (Recommended):**

Use `production-ready.sql` for a clean database without sample data:

```bash
# Create your database first
mysql -u root -p -e "CREATE DATABASE your_database_name;"

# Import production-ready SQL
mysql -u root -p your_database_name < database/production-ready.sql
```

**For phpMyAdmin (Shared Hosting):**

1. Create database via cPanel → MySQL Databases
2. Note the full database name (with prefix)
3. Open phpMyAdmin, select your database
4. Import `database/production-ready.sql`

**For Development/Testing:**

Use `complete-database-setup.sql` which includes sample data for testing:

```bash
# Create your database first
mysql -u root -p -e "CREATE DATABASE your_database_name;"

# Import complete setup with sample data
mysql -u root -p your_database_name < database/complete-database-setup.sql
```

📚 **Need help choosing?** See [database/SQL_FILES_COMPARISON.md](database/SQL_FILES_COMPARISON.md) for detailed comparison.

📖 **Production deployment?** See [database/PRODUCTION_DATABASE_GUIDE.md](database/PRODUCTION_DATABASE_GUIDE.md) for step-by-step guide.

### 3. Configuration

1. Copy the `.env.example` file to `.env`:
```bash
cp .env.example .env
```

2. Edit `.env` file with your database credentials:
```
DB_HOST=localhost
DB_NAME=your_database_name  # Use full name with prefix on shared hosting
DB_USER=root
DB_PASS=your_password
CURRENCY=NPR
TAX_RATE=13
```

### 4. File Permissions

Ensure the `uploads` directory is writable:
```bash
chmod -R 755 uploads/
```

### 5. Web Server Configuration

#### Apache
Add this to your `.htaccess` file (already included):
```apache
RewriteEngine On
RewriteBase /
```

#### Nginx
Add this to your server configuration:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 6. Access the Application

- **User Frontend**: http://localhost/venubooking/
- **Admin Panel**: http://localhost/venubooking/admin/

## Default Admin Credentials

```
Username: admin
Password: Admin@123
```

**Important**: Change the default password after first login!

## Sample Data

The system comes with pre-populated sample data:

### Venues (4)
- Royal Palace (Kathmandu)
- Garden View Hall (Lalitpur)
- City Convention Center (Kathmandu)
- Lakeside Resort (Pokhara)

### Halls (8)
- Sagarmatha Hall (700 pax, Rs. 150,000)
- Everest Hall (500 pax, Rs. 120,000)
- Garden Lawn (1000 pax, Rs. 180,000)
- Rose Hall (300 pax, Rs. 80,000)
- Convention Hall A (800 pax, Rs. 200,000)
- Convention Hall B (400 pax, Rs. 100,000)
- Lakeview Terrace (600 pax, Rs. 220,000)
- Sunset Hall (350 pax, Rs. 90,000)

### Menus (5)
- Royal Gold Menu (Rs. 2,399/pax)
- Silver Deluxe Menu (Rs. 1,899/pax)
- Bronze Classic Menu (Rs. 1,499/pax)
- Vegetarian Special (Rs. 1,299/pax)
- Premium Platinum (Rs. 2,999/pax)

### Additional Services (8)
- Flower Decoration (Rs. 15,000)
- Stage Decoration (Rs. 25,000)
- Photography Package (Rs. 30,000)
- Videography Package (Rs. 40,000)
- DJ Service (Rs. 20,000)
- Live Band (Rs. 50,000)
- Transportation (Rs. 35,000)
- Valet Parking (Rs. 10,000)

## User Guide

### Making a Booking

1. **Step 1**: Enter event details
   - Select shift (Morning/Afternoon/Evening/Full Day)
   - Choose event date (must be future date)
   - Enter number of guests (minimum 10)
   - Select event type

2. **Step 2**: Select venue and hall
   - Browse available venues
   - View halls within selected venue
   - Check hall capacity and features
   - Select desired hall

3. **Step 3**: Choose menus
   - Select one or multiple menus
   - View menu items and pricing
   - See real-time price updates

4. **Step 4**: Add services (optional)
   - Select additional services
   - View service categories
   - See updated total cost

5. **Step 5**: Enter information
   - Provide customer details
   - Add special requests
   - Review booking summary
   - Confirm booking

6. **Confirmation**: View booking details
   - Booking number generated
   - Complete booking information
   - Print or save confirmation

## Admin Guide

### Managing Bookings

1. Navigate to **Bookings** section
2. View all bookings in a sortable table
3. Update booking status (Pending/Confirmed/Cancelled/Completed)
4. Update payment status (Unpaid/Partial/Paid)
5. View detailed booking information

### Managing Venues & Halls

1. Add venues with location and contact details
2. Add halls linked to venues
3. Set hall capacity and base pricing
4. Assign available menus to halls
5. Upload images for venues and halls

### Managing Menus

1. Create menus with pricing per person
2. Add menu items in categories
3. Link menus to specific halls
4. Update menu details anytime

### Viewing Reports

1. Access the Reports section
2. View monthly revenue charts
3. See booking statistics
4. Export data as needed

## Technical Details

### Technology Stack
- **Backend**: PHP 8.x with PDO
- **Frontend**: HTML5, CSS3, JavaScript, jQuery
- **CSS Framework**: Bootstrap 5
- **Database**: MySQL 8.x
- **Libraries**: 
  - DataTables for admin tables
  - Chart.js for analytics
  - SweetAlert2 for alerts
  - Font Awesome for icons

### Security Features
- SQL injection prevention (PDO prepared statements)
- XSS protection (htmlspecialchars)
- CSRF token protection
- Password hashing (bcrypt)
- Session security
- Input validation

### File Structure
```
venubooking/
├── index.php                 # Landing page
├── booking-step2.php         # Venue & hall selection
├── booking-step3.php         # Menu selection
├── booking-step4.php         # Additional services
├── booking-step5.php         # Customer info & booking
├── confirmation.php          # Booking confirmation
├── config/
│   └── database.php          # Database configuration
├── includes/
│   ├── db.php                # Database connection
│   ├── functions.php         # Core functions
│   ├── auth.php              # Authentication
│   ├── header.php            # Frontend header
│   └── footer.php            # Frontend footer
├── css/
│   ├── style.css             # Main styles
│   ├── booking.css           # Booking styles
│   └── responsive.css        # Responsive styles
├── js/
│   ├── main.js               # Main JavaScript
│   ├── booking-flow.js       # Booking flow logic
│   ├── booking-step2.js      # Step 2 logic
│   ├── booking-step3.js      # Step 3 logic
│   ├── booking-step4.js      # Step 4 logic
│   └── price-calculator.js   # Price calculation
├── uploads/                  # Uploaded images
├── admin/
│   ├── login.php             # Admin login
│   ├── dashboard.php         # Admin dashboard
│   ├── venues/               # Venue management
│   ├── halls/                # Hall management
│   ├── menus/                # Menu management
│   ├── bookings/             # Booking management
│   ├── customers/            # Customer management
│   ├── services/             # Service management
│   ├── reports/              # Reports & analytics
│   └── settings/             # System settings
├── api/
│   ├── check-availability.php
│   ├── get-halls.php
│   ├── select-hall.php
│   └── calculate-price.php
└── database/
    ├── schema.sql            # Database schema
    └── sample-data.sql       # Sample data
```

## Troubleshooting

### Database Connection Error
- Check database credentials in `.env` file
- Ensure MySQL service is running
- Verify database exists and user has proper permissions

### Images Not Displaying
- Check file permissions on `uploads/` directory
- Ensure images are uploaded to correct subdirectories
- Verify image paths in database

### Session Issues
- Check PHP session configuration
- Ensure cookies are enabled in browser
- Clear browser cache and cookies

### Booking Not Saving
- Check database connection
- Verify all required fields are filled
- Check PHP error logs for details

## Support

For issues and questions:
- Check the documentation above
- Review troubleshooting section
- Contact: info@venubooking.com

## License

This project is proprietary software. All rights reserved.

## Credits

Developed by Marriage Station Pvt Ltd

---

**Version**: 1.0.0  
**Last Updated**: January 2026