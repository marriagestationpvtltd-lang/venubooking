#!/bin/bash
# Script to apply company settings migration for invoice/bill printing feature

echo "============================================"
echo "Applying Company Settings Migration"
echo "============================================"
echo ""

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo "❌ Error: .env file not found!"
    echo "Please create a .env file with your database credentials."
    echo ""
    echo "Example:"
    echo "DB_HOST=localhost"
    echo "DB_NAME=venubooking"
    echo "DB_USER=root"
    echo "DB_PASS=your_password"
    exit 1
fi

# Load environment variables
source .env

# Check if database credentials are set
if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
    echo "❌ Error: Database credentials not properly configured in .env"
    exit 1
fi

echo "📊 Database: $DB_NAME"
echo "🖥️  Host: $DB_HOST"
echo ""

# Apply migration
echo "Applying company settings migration..."
if [ -z "$DB_PASS" ]; then
    mysql -h"$DB_HOST" -u"$DB_USER" "$DB_NAME" < database/migrations/add_company_settings.sql
else
    # Use secure password input to avoid exposing password in process list
    mysql -h"$DB_HOST" -u"$DB_USER" -p "$DB_NAME" < database/migrations/add_company_settings.sql
fi

if [ $? -eq 0 ]; then
    echo "✅ Company settings migration applied successfully!"
    echo ""
    echo "📋 Next Steps:"
    echo "1. Go to Admin Panel -> Settings -> Company/Invoice tab"
    echo "2. Fill in your company details (name, address, phone, email)"
    echo "3. Upload your company logo for invoices"
    echo "4. These details will appear on all printed booking bills"
    echo ""
else
    echo "❌ Error: Failed to apply migration"
    exit 1
fi
