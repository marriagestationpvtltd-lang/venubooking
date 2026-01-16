#!/bin/bash
# Apply advance payment received migration
# This script adds the advance_payment_received field to the bookings table

echo "Applying advance payment received migration..."

# Check if .env file exists
if [ ! -f .env ]; then
    echo "Error: .env file not found. Please create it from .env.example"
    exit 1
fi

# Load database credentials from .env file
source .env

# Apply migration
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < database/migrations/add_advance_payment_received.sql

if [ $? -eq 0 ]; then
    echo "✓ Migration applied successfully!"
    echo "The 'advance_payment_received' field has been added to the bookings table."
else
    echo "✗ Migration failed. Please check your database credentials and try again."
    exit 1
fi
