# Quick Reference: Which SQL File to Use?

## 🚀 I want to deploy to production/live server
→ Use `production-ready.sql`
→ Read: [PRODUCTION_DATABASE_GUIDE.md](PRODUCTION_DATABASE_GUIDE.md)

## 🧪 I want to test/develop locally
→ Use `complete-database-setup.sql`
→ Includes sample data for testing

## ❓ I'm not sure
→ Read: [SQL_FILES_COMPARISON.md](SQL_FILES_COMPARISON.md)
→ Default to `production-ready.sql` (safer)

## 📊 Quick Comparison

| What I Need | File to Use |
|-------------|-------------|
| Clean database for production | `production-ready.sql` ✅ |
| Sample data for testing | `complete-database-setup.sql` |
| Reference only | `schema.sql` (outdated) |

## 🎯 File Contents

### production-ready.sql
- ✅ All 18 tables
- ✅ Admin user
- ✅ System settings
- ✅ Payment methods (inactive)
- ❌ NO sample data

### complete-database-setup.sql  
- ✅ All 18 tables
- ✅ Admin user
- ✅ System settings
- ✅ Payment methods
- ✅ 4 sample venues
- ✅ 8 sample halls
- ✅ 5 sample menus
- ✅ Test bookings

## ⚡ Quick Start Commands

### Production:
```bash
mysql -u root -p -e "CREATE DATABASE venubooking_prod;"
mysql -u root -p venubooking_prod < database/production-ready.sql
```

### Development:
```bash
mysql -u root -p -e "CREATE DATABASE venubooking_dev;"
mysql -u root -p venubooking_dev < database/complete-database-setup.sql
```

## 🔒 Default Credentials

Both files include:
- Username: `admin`
- Password: `Admin@123`
- ⚠️ **CHANGE IMMEDIATELY** after installation

## �� More Information

- **Production Guide:** [PRODUCTION_DATABASE_GUIDE.md](PRODUCTION_DATABASE_GUIDE.md)
- **Comparison:** [SQL_FILES_COMPARISON.md](SQL_FILES_COMPARISON.md)
- **Full Docs:** [README.md](README.md)

---
**When in doubt, use `production-ready.sql`** - it's safer and cleaner!
