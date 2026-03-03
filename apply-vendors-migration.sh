#!/bin/bash
# Apply vendors migration
# This script creates the vendors and booking_vendor_assignments tables
# to enable assigning service providers (Pandit, Photographer, Videographer, Baje, etc.) to bookings.

echo "Applying vendors migration..."

# Check if .env file exists
if [ ! -f .env ]; then
    echo "Error: .env file not found. Please create it from .env.example"
    exit 1
fi

# Load database credentials from .env file
source .env

# Apply migration
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < database/migrations/add_vendors.sql

if [ $? -eq 0 ]; then
    echo "✓ Migration applied successfully!"
    echo "The 'vendors' and 'booking_vendor_assignments' tables have been created."
    echo ""
    echo "Next steps:"
    echo "  1. Go to Admin Panel > Vendors to add your service providers."
    echo "  2. Open any booking and use the 'Vendor Assignments' section to assign vendors."
else
    echo "✗ Migration failed. Please check your database credentials and try again."
    exit 1
fi
