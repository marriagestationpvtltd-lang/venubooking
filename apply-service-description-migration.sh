#!/bin/bash

# Apply Service Description and Category Migration
# This script adds description and category columns to booking_services table

echo "======================================"
echo "Service Description/Category Migration"
echo "======================================"
echo ""

# Get database credentials
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
elif [ -f config/database.php ]; then
    echo "Reading database config from config/database.php..."
    DB_HOST=$(php -r "include 'config/database.php'; echo DB_HOST;")
    DB_NAME=$(php -r "include 'config/database.php'; echo DB_NAME;")
    DB_USER=$(php -r "include 'config/database.php'; echo DB_USER;")
    DB_PASS=$(php -r "include 'config/database.php'; echo DB_PASS;")
else
    echo "Please enter your database credentials:"
    read -p "Database host [localhost]: " DB_HOST
    DB_HOST=${DB_HOST:-localhost}
    read -p "Database name [venubooking]: " DB_NAME
    DB_NAME=${DB_NAME:-venubooking}
    read -p "Database user [root]: " DB_USER
    DB_USER=${DB_USER:-root}
    read -sp "Database password: " DB_PASS
    echo ""
fi

echo ""
echo "Applying migration to database: $DB_NAME"
echo ""

# Apply migration securely
# Note: Using -p with password in command line can expose it in process lists
# For production, consider using mysql_config_editor or .my.cnf file
if [ -z "$DB_PASS" ]; then
    # No password provided, prompt securely
    mysql -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" < database/migrations/add_service_description_category_to_bookings.sql
else
    # Password provided (development environment)
    # Create temporary config file for secure password passing
    TMP_CNF=$(mktemp)
    cat > "$TMP_CNF" << EOF
[client]
password=$DB_PASS
EOF
    chmod 600 "$TMP_CNF"
    
    mysql --defaults-extra-file="$TMP_CNF" -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" < database/migrations/add_service_description_category_to_bookings.sql
    
    # Clean up temporary file
    rm -f "$TMP_CNF"
fi

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Migration applied successfully!"
    echo ""
    echo "What changed:"
    echo "- Added 'description' column to booking_services table"
    echo "- Added 'category' column to booking_services table"
    echo "- Updated existing records with data from master table"
    echo ""
    echo "Benefits:"
    echo "- Full historical data preservation for booked services"
    echo "- Services retain description/category even if deleted from master table"
    echo "- Better display of service details in booking views and invoices"
    echo ""
    echo "Next steps:"
    echo "1. Test creating a new booking with services"
    echo "2. Verify services display with description and category"
    echo "3. Test editing existing bookings"
else
    echo ""
    echo "❌ Migration failed. Please check error messages above."
    echo ""
    exit 1
fi
