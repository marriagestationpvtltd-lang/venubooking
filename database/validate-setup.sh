#!/bin/bash
# Database Setup Validation Script
# This script validates the SQL files for syntax errors and completeness

echo "=== Database SQL Validation Script ==="
echo ""

# Check if mysql client is available
if ! command -v mysql &> /dev/null; then
    echo "❌ MySQL client not found"
    exit 1
fi

echo "✅ MySQL client found"
echo ""

# Production-ready files (recommended)
PROD_FILES=(
    "database/production-ready.sql"
    "database/production-shared-hosting.sql"
)

# Development/testing file
DEV_FILES=(
    "database/complete-database-setup.sql"
)

# Required tables that every setup file must define
REQUIRED_TABLES=(
    "cities"
    "venues"
    "venue_images"
    "halls"
    "hall_images"
    "hall_menus"
    "menus"
    "menu_items"
    "additional_services"
    "service_categories"
    "service_packages"
    "service_package_features"
    "service_package_photos"
    "customers"
    "bookings"
    "booking_menus"
    "booking_services"
    "payment_methods"
    "booking_payment_methods"
    "payments"
    "vendor_types"
    "vendors"
    "vendor_photos"
    "booking_vendor_assignments"
    "users"
    "settings"
    "activity_logs"
    "site_images"
)

# Required columns that must exist in specific tables
check_required_columns() {
    local file="$1"
    local errors=0

    # hall_menus must have status column
    # Use awk to extract only the hall_menus CREATE TABLE block (up to closing paren + semicolon)
    if ! awk '/CREATE TABLE hall_menus/,/\) ENGINE=/' "$file" | grep -q "status"; then
        echo "   ❌ MISSING: hall_menus.status column"
        errors=$((errors + 1))
    else
        echo "   ✅ hall_menus.status present"
    fi

    # site_images must have event_category column
    if ! awk '/CREATE TABLE site_images/,/\) ENGINE=/' "$file" | grep -q "event_category"; then
        echo "   ❌ MISSING: site_images.event_category column"
        errors=$((errors + 1))
    else
        echo "   ✅ site_images.event_category present"
    fi

    return $errors
}

# Required settings keys that must be in INSERT INTO settings
REQUIRED_SETTINGS=(
    "site_name"
    "site_logo"
    "site_favicon"
    "company_logo"
    "contact_email"
    "contact_phone"
    "tax_rate"
    "advance_payment_percentage"
    "email_enabled"
    "smtp_enabled"
    "footer_about"
    "meta_title"
    "social_facebook"
    "whatsapp_number"
    "quick_links"
)

check_required_settings() {
    local file="$1"
    local errors=0

    for key in "${REQUIRED_SETTINGS[@]}"; do
        if ! grep -q "'$key'" "$file"; then
            echo "   ❌ MISSING setting: $key"
            errors=$((errors + 1))
        fi
    done

    if [ $errors -eq 0 ]; then
        echo "   ✅ All required settings present"
    fi

    return $errors
}

validate_file() {
    local file="$1"
    local total_errors=0

    echo "📄 Checking: $file"

    if [ ! -f "$file" ]; then
        echo "   ❌ File not found"
        echo ""
        return 1
    fi

    # Basic SQL presence check
    if ! grep -qE "CREATE TABLE|INSERT INTO" "$file"; then
        echo "   ⚠️  Warning: No SQL commands found"
        echo ""
        return 1
    fi

    # Count tables
    table_count=$(grep -c "CREATE TABLE" "$file" 2>/dev/null || echo "0")
    echo "   📊 Creates $table_count tables"

    # Check required tables
    missing_tables=0
    for tbl in "${REQUIRED_TABLES[@]}"; do
        if ! grep -q "CREATE TABLE.*$tbl\b\|CREATE TABLE IF NOT EXISTS.*$tbl\b" "$file"; then
            echo "   ❌ MISSING table: $tbl"
            missing_tables=$((missing_tables + 1))
        fi
    done
    if [ $missing_tables -eq 0 ]; then
        echo "   ✅ All ${#REQUIRED_TABLES[@]} required tables present"
    fi
    total_errors=$((total_errors + missing_tables))

    # Check required columns
    echo "   Checking required columns..."
    check_required_columns "$file"
    col_errors=$?
    total_errors=$((total_errors + col_errors))

    # Check required settings
    echo "   Checking required settings..."
    check_required_settings "$file"
    set_errors=$?
    total_errors=$((total_errors + set_errors))

    # Count insert statements
    insert_count=$(grep -c "INSERT INTO" "$file" 2>/dev/null || echo "0")
    echo "   📊 Contains $insert_count INSERT statements"

    if [ $total_errors -eq 0 ]; then
        echo "   ✅ PASSED - File is complete and valid"
    else
        echo "   ❌ FAILED - $total_errors issue(s) found"
    fi
    echo ""
}

echo "--- Production Files ---"
echo ""
for file in "${PROD_FILES[@]}"; do
    validate_file "$file"
done

echo "--- Development Files ---"
echo ""
for file in "${DEV_FILES[@]}"; do
    validate_file "$file"
done

echo "=== Validation Complete ==="
echo ""
echo "To apply the database setup, run one of these commands:"
echo ""
echo "For Production (VPS/Dedicated):"
echo "  mysql -u root -p your_database < database/production-ready.sql"
echo ""
echo "For Production (Shared Hosting):"
echo "  mysql -u your_user -p your_database < database/production-shared-hosting.sql"
echo ""
echo "For Development/Testing:"
echo "  mysql -u root -p your_database < database/complete-database-setup.sql"
echo ""
