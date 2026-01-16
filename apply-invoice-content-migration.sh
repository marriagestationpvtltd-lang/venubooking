#!/bin/bash

# Invoice Content Settings Migration Script
# This script applies the invoice content settings migration

echo "================================================"
echo "Invoice Content Settings Migration"
echo "================================================"
echo ""

# Get database credentials
echo "Please provide your database details:"
read -p "Database host (default: localhost): " DB_HOST
DB_HOST=${DB_HOST:-localhost}

read -p "Database name: " DB_NAME
read -p "Database username: " DB_USER
read -sp "Database password: " DB_PASSWORD
echo ""
echo ""

# Apply the migration
echo "Applying invoice content settings migration..."

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < database/migrations/add_invoice_content_settings.sql

if [ $? -eq 0 ]; then
    echo ""
    echo "✓ Migration applied successfully!"
    echo ""
    echo "The following settings have been added:"
    echo "  - invoice_title (Invoice header title)"
    echo "  - cancellation_policy (Cancellation policy text)"
    echo "  - invoice_disclaimer (Invoice disclaimer note)"
    echo ""
    echo "You can now customize these in Admin Panel > Settings > Company/Invoice tab"
else
    echo ""
    echo "✗ Migration failed! Please check your database credentials and try again."
    exit 1
fi

echo ""
echo "================================================"
echo "Migration Complete"
echo "================================================"
