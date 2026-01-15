#!/bin/bash

# Booking Payment Confirmation Feature Migration Script
# This script applies the database changes for the payment confirmation feature

echo "========================================"
echo "Booking Payment Confirmation Migration"
echo "========================================"
echo ""

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo "Error: .env file not found!"
    echo "Please copy .env.example to .env and configure your database settings."
    exit 1
fi

# Load database credentials from .env file
export $(cat .env | grep -v '^#' | xargs)

# Check if required variables are set
if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
    echo "Error: Database configuration not found in .env file!"
    echo "Please ensure DB_HOST, DB_NAME, and DB_USER are set."
    exit 1
fi

# Set password for MySQL (if empty, don't use -p flag)
MYSQL_CMD="mysql -h $DB_HOST -u $DB_USER"
if [ ! -z "$DB_PASS" ]; then
    MYSQL_CMD="$MYSQL_CMD -p$DB_PASS"
fi

echo "Database: $DB_NAME"
echo "Host: $DB_HOST"
echo "User: $DB_USER"
echo ""

# Apply migration
echo "Applying payment confirmation feature migration..."
$MYSQL_CMD $DB_NAME < database/migrations/add_booking_payment_confirmation.sql

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Migration applied successfully!"
    echo ""
    echo "The following tables were created/updated:"
    echo "  - payment_methods (payment gateway/methods management)"
    echo "  - booking_payment_methods (junction table)"
    echo "  - payments (payment transactions)"
    echo "  - bookings (updated booking_status enum)"
    echo "  - settings (added advance_payment_percentage)"
    echo ""
    echo "Next steps:"
    echo "1. Log in to the admin panel"
    echo "2. Go to Settings and configure advance payment percentage"
    echo "3. Go to Payment Methods to configure your payment options"
    echo "4. Users can now submit payments during booking"
    echo ""
else
    echo ""
    echo "❌ Migration failed!"
    echo "Please check the error messages above."
    echo ""
    exit 1
fi
