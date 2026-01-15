#!/bin/bash
# Script to apply payment methods migration

echo "Applying Payment Methods Migration..."

# Load database credentials from .env if it exists
if [ -f .env ]; then
    source .env
else
    echo "Warning: .env file not found. Using default values."
    DB_HOST="localhost"
    DB_NAME="venubooking"
    DB_USER="root"
    DB_PASS=""
fi

# Run migration
if [ -z "$DB_PASS" ]; then
    mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" < database/migrations/add_payment_methods.sql
else
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migrations/add_payment_methods.sql
fi

if [ $? -eq 0 ]; then
    echo "✓ Payment methods migration applied successfully!"
    echo ""
    echo "Next steps:"
    echo "1. Go to Admin Panel > Payment Methods to configure your payment methods"
    echo "2. Add payment methods like Bank Transfer, eSewa, Khalti, etc."
    echo "3. Upload QR codes and add bank details"
    echo "4. Link payment methods to bookings when creating or editing bookings"
    echo ""
else
    echo "✗ Migration failed. Please check your database connection and try again."
    exit 1
fi
