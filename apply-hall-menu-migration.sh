#!/bin/bash
# Apply Hall-Menu Assignment Status Migration
# Run this script to add the status column to hall_menus table

set -e

echo "======================================"
echo "Hall-Menu Assignment Status Migration"
echo "======================================"
echo ""

# Check if .env file exists
if [ -f ".env" ]; then
    source .env
else
    echo "Error: .env file not found!"
    echo "Please create a .env file with your database credentials."
    exit 1
fi

# Validate required environment variables
if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
    echo "Error: Missing required database credentials!"
    echo "Please ensure .env file contains:"
    echo "  DB_USER=your_username"
    echo "  DB_PASS=your_password"
    echo "  DB_NAME=your_database"
    echo "  DB_HOST=localhost (optional, defaults to localhost)"
    exit 1
fi

# Apply migration
echo "Applying migration to add status column to hall_menus table..."
mysql -h "${DB_HOST:-localhost}" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migrations/add_hall_menus_status.sql

if [ $? -eq 0 ]; then
    echo ""
    echo "✓ Migration applied successfully!"
    echo ""
    echo "Next steps:"
    echo "1. Go to Admin → Halls → Edit Hall"
    echo "2. Assign menus to each hall"
    echo "3. Test booking flow to see hall-specific menus"
    echo ""
else
    echo ""
    echo "✗ Migration failed!"
    echo "Please check your database credentials and try again."
    exit 1
fi
