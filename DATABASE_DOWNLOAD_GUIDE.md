# 📦 Database Download & Upload Instructions

## ✅ COMPLETED: Your Production Database is Ready!

Your database files have been created and are ready for upload to your shared hosting environment.

---

## 📥 Files to Download

### **1. Main Database File** (REQUIRED)
```
📄 database/production-shared-hosting.sql
```
This file contains:
- All 18 database tables
- Default admin user (admin/Admin@123)
- 4 sample venues
- 8 sample halls with images
- 5 food menus with items
- 8 additional services
- 4 payment methods
- 7 sample customers
- 4 test bookings (including #23 and #37)
- Complete payment records

**Size**: ~34 KB  
**Format**: SQL file for phpMyAdmin import

### **2. Environment Configuration** (REQUIRED)
```
📄 .env.production
```
Pre-configured with your database credentials:
```
DB_HOST=localhost
DB_NAME=digitallami_partybooking
DB_USER=digitallami_partybooking
DB_PASS=P@sswo0rdms
```

### **3. Setup Guide** (RECOMMENDED)
```
📄 SHARED_HOSTING_SETUP.md
```
Complete step-by-step instructions for:
- Importing database via phpMyAdmin
- Configuring the application
- Security best practices
- Troubleshooting common issues

---

## 🚀 Quick Upload Process (3 Steps)

### Step 1: Import Database (2 minutes)
1. Open **phpMyAdmin** from your cPanel
2. Click on database **digitallami_partybooking** in left sidebar
3. Click **Import** tab → Choose File → Select **production-shared-hosting.sql**
4. Click **Go** and wait for success message

### Step 2: Configure Application (1 minute)
1. Upload all website files to your hosting
2. Rename **.env.production** to **.env** in root directory
3. Set folder permissions: `chmod 755 uploads/`

### Step 3: Login & Secure (2 minutes)
1. Visit: `https://yoursite.com/admin/`
2. Login: **admin** / **Admin@123**
3. **⚠️ IMMEDIATELY change password** in Settings!

---

## 📊 What's Included in Database

### Complete Test Data

✅ **4 Venues**
- Royal Palace (Kathmandu)
- Garden View Hall (Lalitpur)
- City Convention Center (Kathmandu)
- Lakeside Resort (Pokhara)

✅ **8 Halls** (Capacity: 300-1000 guests)
- Sagarmatha Hall (700)
- Everest Hall (500)
- Garden Lawn (1000)
- Rose Hall (300)
- Convention Hall A (800)
- Convention Hall B (400)
- Lakeview Terrace (600)
- Sunset Hall (350)

✅ **5 Food Menus**
- Premium Platinum: NPR 2,999/person
- Royal Gold Menu: NPR 2,399/person
- Silver Deluxe Menu: NPR 1,899/person
- Bronze Classic Menu: NPR 1,499/person
- Vegetarian Special: NPR 1,299/person

✅ **8 Additional Services**
- Flower Decoration: NPR 15,000
- Stage Decoration: NPR 25,000
- Photography Package: NPR 30,000
- Videography Package: NPR 40,000
- DJ Service: NPR 20,000
- Live Band: NPR 50,000
- Transportation: NPR 35,000
- Valet Parking: NPR 10,000

✅ **4 Sample Bookings**
- Booking #1: Wedding (500 guests) - NPR 1,598,385.00
- Booking #2: Birthday Party (200 guests) - NPR 593,024.00
- **Booking #23**: Wedding Reception (250 guests) - NPR 604,267.50
- **Booking #37**: Wedding Ceremony (600 guests) - NPR 1,570,022.00

---

## 🔒 Critical Security Reminders

### ⚠️ DO IMMEDIATELY AFTER IMPORT:

1. **Change Admin Password**
   - Current: admin / Admin@123
   - Change in: Admin Panel → Settings → Change Password

2. **Update Company Information**
   - Admin Panel → Settings
   - Update your real business details

3. **Configure Payment Methods**
   - Admin Panel → Payment Methods
   - Add your real bank details, QR codes
   - Activate when ready to accept payments

4. **Secure .env File**
   ```bash
   chmod 600 .env
   ```

---

## 📂 Complete File Structure

```
venubooking/
├── database/
│   └── production-shared-hosting.sql  ← Download & Import this
├── .env.production                     ← Rename to .env
└── SHARED_HOSTING_SETUP.md            ← Read this for detailed instructions
```

---

## ✅ Verification Checklist

After import, verify everything is working:

### Database Check (phpMyAdmin)
```sql
-- Check tables (should show 18)
SHOW TABLES;

-- Check bookings (should return 4 rows)
SELECT * FROM bookings;

-- Check admin user
SELECT username FROM users WHERE role = 'admin';
```

### Website Check
- [ ] Homepage loads: `https://yoursite.com/`
- [ ] Can browse venues and halls
- [ ] Admin panel accessible: `https://yoursite.com/admin/`
- [ ] Can login with admin credentials
- [ ] Dashboard shows test bookings
- [ ] Sample data visible throughout admin panel

---

## 🎯 Ready to Go Live?

### Replace Test Data with Real Data

When ready to use with real customers:

1. **Keep the structure** (venues, halls, menus, services)
2. **Delete test bookings**: Admin → Bookings → Delete test entries
3. **Update venues/halls**: Edit with your real venue information
4. **Adjust pricing**: Update menu and service prices
5. **Delete sample customers**: If not needed

### Or Keep Test Data

You can also keep the test data for:
- Demonstrating the system to clients
- Training staff
- Testing features
- Reference examples

Just start adding real bookings alongside test data!

---

## 📞 Need Help?

### Detailed Documentation
- **[SHARED_HOSTING_SETUP.md](SHARED_HOSTING_SETUP.md)** - Complete setup guide
- **[database/README.md](database/README.md)** - Database documentation

### Common Issues

**"Cannot connect to database"**
→ Check .env file credentials match database

**"Table doesn't exist"**
→ Re-import production-shared-hosting.sql

**"Permission denied for uploads"**
→ Run: `chmod 755 uploads/` and subdirectories

---

## 🎉 Summary

You now have:

✅ Complete production database file with test data
✅ Pre-configured credentials for your shared hosting
✅ Comprehensive setup documentation
✅ Sample bookings for immediate demonstration
✅ All 18 required tables with proper relationships
✅ Default admin access for system management

**Total Setup Time**: ~5 minutes  
**Database Size**: 34 KB  
**Test Bookings**: 4 (including requested #23 and #37)

---

**Database Credentials (Already in .env.production):**
```
Database: digitallami_partybooking
Username: digitallami_partybooking  
Password: P@sswo0rdms
```

**Admin Login (Change immediately!):**
```
Username: admin
Password: Admin@123
URL: /admin/
```

---

📅 **Created**: January 2026  
🔖 **Version**: Production 2.0  
✨ **Status**: Ready for deployment
