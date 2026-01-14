# Venue Booking System

A complete, production-ready venue booking system built with PHP, MySQL, and Bootstrap 5. Features a comprehensive admin panel and a user-friendly booking workflow.

## 🌟 Features

### Frontend (Customer-Facing)
- **Step-by-Step Booking Flow**
  - Step 1: Event details (date, shift, guests, event type)
  - Step 2: Venue and hall selection with real-time availability checking
  - Step 3: Menu selection (multiple menus supported)
  - Step 4: Additional services (decoration, photography, etc.)
  - Step 5: Customer information and payment options
  - Step 6: Booking confirmation with email notification

- **Real-Time Features**
  - Hall availability checking
  - Dynamic price calculation
  - Live booking summary
  - Responsive design (mobile, tablet, desktop)

### Admin Panel
- **Dashboard** - Statistics, charts, recent bookings
- **Venue Management** - Create, edit, delete venues
- **Hall Management** - Manage halls, assign menus, upload images
- **Menu Management** - Create menus with items
- **Booking Management** - View, edit, calendar view, export
- **Customer Management** - View customer details and booking history
- **Services Management** - Manage additional services
- **Reports** - Revenue, bookings, customer reports
- **Settings** - General, booking, email, payment, users

## 📋 Requirements

- **Web Server**: Apache 2.4+ or Nginx
- **PHP**: 8.0 or higher
- **MySQL**: 8.0 or higher
- **PHP Extensions**: PDO, PDO_MySQL, mbstring, json, openssl

## 🚀 Installation

### For cPanel Shared Hosting

**📘 [Complete cPanel Installation Guide](CPANEL_INSTALLATION.md)**

If you're using cPanel shared hosting, please follow the detailed step-by-step guide in [CPANEL_INSTALLATION.md](CPANEL_INSTALLATION.md) which covers:
- Uploading files via File Manager or FTP
- Creating MySQL database with cPanel
- Importing database using phpMyAdmin
- Configuring .env file
- Setting file permissions
- Common troubleshooting issues

### For Local/VPS Installation

#### Step 1: Clone or Download

```bash
git clone https://github.com/marriagestationpvtltd-lang/venubooking.git
cd venubooking
```

#### Step 2: Database Setup

1. Create a new MySQL database:
```sql
CREATE DATABASE venubooking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the database schema:
```bash
mysql -u your_username -p venubooking < database/schema.sql
```

3. Import sample data (optional but recommended):
```bash
mysql -u your_username -p venubooking < database/sample-data.sql
```

#### Step 3: Configuration

1. Copy the environment configuration file:
```bash
cp .env.example .env
```

2. Edit `.env` and update your database credentials:
```env
DB_HOST=localhost
DB_NAME=venubooking
DB_USER=your_username
DB_PASS=your_password
```

3. Configure other settings as needed.

#### Step 4: File Permissions

Ensure the uploads directory is writable:
```bash
chmod -R 775 uploads/
chown -R www-data:www-data uploads/
```

#### Step 5: Access the System

1. **Frontend**: http://localhost/venubooking/
2. **Admin Panel**: http://localhost/venubooking/admin/

## 🔐 Default Admin Credentials

- **Username**: `admin`
- **Password**: `Admin@123`

**⚠️ IMPORTANT**: Change the default password immediately after first login!

## 📊 Database Structure

The system uses 14 tables with complete relationships and constraints.

## 💡 Key Features

### Security
- SQL injection prevention (PDO prepared statements)
- XSS protection (output sanitization)
- CSRF token protection
- Password hashing (bcrypt)
- Session security
- File upload validation

### Double Booking Prevention
Unique constraint on (hall_id, booking_date, shift) prevents double bookings.

### Price Calculation
Automatic calculation of hall price + menu cost + services + tax.

### Email Notifications
Booking confirmations, cancellations, and reminders.

## 🎨 Design

- Green color scheme (#4CAF50, #2E7D32)
- Responsive design (Bootstrap 5)
- Mobile-first approach
- Professional UI/UX

## 📞 Support

For issues or questions, please open an issue on GitHub.

## 📄 License

This project is proprietary software. All rights reserved.

---

**© 2026 Venue Booking System. All rights reserved.**