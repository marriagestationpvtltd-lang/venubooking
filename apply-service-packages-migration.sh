#!/bin/bash

# Script to apply service packages migration
# Usage: ./apply-service-packages-migration.sh

echo "================================================"
echo "Service Packages Migration"
echo "================================================"
echo ""

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo "Error: .env file not found!"
    echo "Please create .env file with database credentials"
    exit 1
fi

# Load database credentials from .env
source .env

# Check if required variables are set
if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
    echo "Error: Database credentials not properly configured in .env"
    echo "Required: DB_HOST, DB_NAME, DB_USER, DB_PASS"
    exit 1
fi

echo "Database: $DB_NAME"
echo "Host: $DB_HOST"
echo ""

# Check if migration file exists
MIGRATION_FILE="database/migrations/add_service_packages.sql"
if [ ! -f "$MIGRATION_FILE" ]; then
    echo "Error: Migration file not found: $MIGRATION_FILE"
    exit 1
fi

echo "Applying migration: $MIGRATION_FILE"
echo ""

# Apply migration
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MIGRATION_FILE"

if [ $? -eq 0 ]; then
    echo ""
    echo "================================================"
    echo "Migration completed successfully!"
    echo "================================================"
    echo ""
    echo "Service packages tables have been created."
    echo "You can now manage categories and packages from the admin panel."
    echo "Navigate to: Admin Panel -> Packages"
else
    echo ""
    echo "================================================"
    echo "Migration failed!"
    echo "================================================"
    echo ""
    echo "Please check the error messages above and try again."
    exit 1
fi
