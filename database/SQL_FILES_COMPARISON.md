# SQL Files Comparison Guide

## 📊 Quick Comparison

| Feature | production-ready.sql | complete-database-setup.sql | schema.sql |
|---------|---------------------|----------------------------|------------|
| **Purpose** | Production/Live deployment | Development/Testing | Reference only |
| **All 18 Tables** | ✅ Yes | ✅ Yes | ❌ No (missing payment tables) |
| **Admin User** | ✅ Yes (admin/Admin@123) | ✅ Yes (admin/Admin@123) | ✅ Yes |
| **System Settings** | ✅ Essential only | ✅ All settings | ✅ Basic only |
| **Payment Methods** | ✅ Placeholders (inactive) | ✅ Configured examples | ❌ No |
| **Sample Venues** | ❌ No | ✅ 4 venues | ❌ No |
| **Sample Halls** | ❌ No | ✅ 8 halls | ❌ No |
| **Sample Menus** | ❌ No | ✅ 5 menus with items | ❌ No |
| **Sample Services** | ❌ No | ✅ 8 services | ❌ No |
| **Sample Customers** | ❌ No | ✅ 7 customers | ❌ No |
| **Test Bookings** | ❌ No | ✅ 4 bookings (#1, #2, #23, #37) | ❌ No |
| **File Size** | ~450 lines | ~642 lines | ~233 lines |
| **Best For** | 🚀 Production websites | 🧪 Development & testing | 📚 Reference only |

## 🎯 Which File Should You Use?

### Use `production-ready.sql` when:

✅ **Deploying to production/live server**
- You want a clean database with no sample data
- You'll add your own venues, halls, menus, and services
- You want placeholder payment methods (to configure yourself)
- You need a professional, empty database ready for real customers

**Perfect for:**
- Live websites
- Client deployments
- Production servers
- Any public-facing system

### Use `complete-database-setup.sql` when:

✅ **Setting up for development or testing**
- You want to explore the system with sample data
- You're learning how the system works
- You need test bookings for development
- You want to see examples of venues, halls, and menus

**Perfect for:**
- Local development
- Testing features
- Demonstrations
- Understanding the data structure

### Don't use `schema.sql`:

❌ **This file is outdated and incomplete**
- Missing payment_methods table
- Missing payment-related tables
- Missing booking_payment_methods table
- No payment methods configuration
- Kept only for reference

## 📋 Detailed Comparison

### Tables Created

#### All three files create these core tables:
- `venues`, `halls`, `hall_images`
- `menus`, `menu_items`, `hall_menus`
- `additional_services`
- `customers`, `bookings`, `booking_menus`, `booking_services`
- `users`, `settings`, `activity_logs`, `site_images`

#### Only production-ready.sql and complete-database-setup.sql create:
- `payment_methods` - Payment method configurations
- `booking_payment_methods` - Links bookings to payment methods
- `payments` - Payment transaction records

### Default Admin User

All files include:
- Username: `admin`
- Password: `Admin@123`
- ⚠️ Must be changed immediately after installation

### System Settings

#### production-ready.sql includes:
```
- site_name: "Venue Booking System"
- contact_email: "info@example.com"
- currency: "NPR"
- tax_rate: "13"
- advance_payment_percentage: "30"
- company_name: "Your Company Name"
- invoice_title: "Booking Confirmation & Payment Receipt"
- cancellation_policy: (default text)
```
*All set to placeholder values requiring configuration*

#### complete-database-setup.sql includes:
*Same as production-ready.sql but with realistic example values*

### Payment Methods

#### production-ready.sql:
- 4 payment methods (Bank Transfer, eSewa, Khalti, Cash)
- All set to **inactive** by default
- Placeholder details with instructions to update
- Requires configuration before activation

#### complete-database-setup.sql:
- 4 payment methods with example details
- Some active (Cash), others inactive
- Includes sample payment transactions
- Ready for testing immediately

#### schema.sql:
- ❌ No payment methods table
- Cannot track payments properly

### Sample Data

#### production-ready.sql:
```
Venues:     0
Halls:      0
Menus:      0
Services:   0
Customers:  0
Bookings:   0
```
*Clean slate for production*

#### complete-database-setup.sql:
```
Venues:     4 (Royal Palace, Garden View Hall, etc.)
Halls:      8 (Various capacities 300-1000 guests)
Menus:      5 (NPR 1,299 to NPR 2,999 per person)
Services:   8 (Decoration, DJ, Photography, etc.)
Customers:  7 (Sample customer records)
Bookings:   4 (Including test bookings #23 and #37)
```
*Ready for immediate testing and exploration*

## 🔄 Migration Between Files

### From complete-database-setup.sql to production-ready.sql:

If you started with the development database and want to clean it for production:

**Option 1: Fresh Import (Recommended)**
```bash
# Backup first (optional)
mysqldump -u user -p database_name > backup.sql

# Drop and recreate
mysql -u user -p -e "DROP DATABASE database_name; CREATE DATABASE database_name;"

# Import production-ready
mysql -u user -p database_name < database/production-ready.sql
```

**Option 2: Manual Cleanup (Not Recommended)**
```sql
-- Delete all sample data (risky - make backup first!)
DELETE FROM payments;
DELETE FROM booking_payment_methods;
DELETE FROM booking_services;
DELETE FROM booking_menus;
DELETE FROM bookings;
DELETE FROM customers;
DELETE FROM hall_menus;
DELETE FROM menu_items;
DELETE FROM menus;
DELETE FROM additional_services;
DELETE FROM hall_images;
DELETE FROM halls;
DELETE FROM venues;
DELETE FROM site_images;

-- Reset auto-increment
ALTER TABLE bookings AUTO_INCREMENT = 1;
ALTER TABLE customers AUTO_INCREMENT = 1;
-- ... repeat for all tables
```

### From schema.sql to production-ready.sql:

The schema.sql is missing payment tables, so you must:

```bash
# Start fresh with production-ready.sql
mysql -u user -p database_name < database/production-ready.sql
```

## 🎬 Usage Examples

### Example 1: New Production Website

**Scenario:** Launching a new venue booking website for a client

**Use:** `production-ready.sql`

**Steps:**
1. Import `production-ready.sql` to production database
2. Change admin password immediately
3. Configure company settings
4. Add client's real venues and halls
5. Configure actual payment methods
6. Go live with clean, professional data

### Example 2: Local Development

**Scenario:** Developer wants to test new features

**Use:** `complete-database-setup.sql`

**Steps:**
1. Import `complete-database-setup.sql` to local database
2. Explore sample venues and halls
3. Test booking flow with existing data
4. Develop new features with realistic test data
5. Use booking #23 and #37 for testing

### Example 3: Client Demo

**Scenario:** Showing the system to a potential client

**Use:** `complete-database-setup.sql`

**Steps:**
1. Set up demo environment
2. Import with sample data
3. Walk through booking process
4. Show admin panel features
5. Client sees realistic example data

## 📝 Best Practices

### For Production:

1. ✅ Always use `production-ready.sql`
2. ✅ Change admin password immediately
3. ✅ Configure all settings before going live
4. ✅ Test with a sample booking first
5. ✅ Set payment methods to inactive until configured
6. ✅ Enable SSL/HTTPS
7. ✅ Set up regular database backups

### For Development:

1. ✅ Use `complete-database-setup.sql` for convenience
2. ✅ Keep separate dev and production databases
3. ✅ Never deploy development data to production
4. ✅ Use the test bookings (#23, #37) for testing features
5. ✅ Experiment freely with sample data

### Migration from Dev to Production:

1. ✅ Use `production-ready.sql` on production server
2. ✅ Manually add real venues/halls/menus (don't copy from dev)
3. ✅ Never copy test bookings to production
4. ✅ Configure payment methods from scratch
5. ✅ Test thoroughly before going live

## ⚠️ Common Mistakes to Avoid

❌ **DON'T:**
- Use `complete-database-setup.sql` on production (sample data leaks)
- Use `schema.sql` (missing payment features)
- Copy development data to production
- Leave default admin password unchanged
- Activate payment methods before configuring them
- Skip security steps

✅ **DO:**
- Use `production-ready.sql` for all production deployments
- Change admin password immediately
- Configure all settings before going live
- Test thoroughly in development first
- Keep production and development databases separate
- Read security warnings in the SQL files

## 🔍 How to Verify Which SQL Was Used

Connect to your database and run:

```sql
-- Check for sample data
SELECT COUNT(*) FROM venues;
SELECT COUNT(*) FROM bookings;

-- Check payment methods status
SELECT name, status FROM payment_methods;

-- Check admin user
SELECT username, full_name FROM users WHERE role = 'admin';
```

**If you see:**
- 0 venues, 0 bookings → `production-ready.sql` ✅
- 4 venues, 4 bookings → `complete-database-setup.sql` (should only be dev)
- No payment_methods table → `schema.sql` (outdated)

## 📞 Support

Need help choosing? Consider:

- **For production/live websites** → Always `production-ready.sql`
- **For testing/development** → Use `complete-database-setup.sql`
- **If unsure** → Start with `production-ready.sql` (safer, cleaner)

---

**Last Updated:** January 2026  
**Purpose:** Help users choose the correct SQL file for their needs
