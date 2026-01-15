#!/bin/bash

# Apply Quick Links Settings Migration

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MIGRATION_FILE="$SCRIPT_DIR/database/migrations/add_quick_links_settings.sql"

echo "================================================"
echo "Quick Links Settings Migration"
echo "================================================"
echo ""

# Check if .env file exists
if [ -f "$SCRIPT_DIR/.env" ]; then
    echo "✓ Found .env file"
    source "$SCRIPT_DIR/.env"
else
    echo "⚠ No .env file found, using defaults"
    DB_HOST="localhost"
    DB_NAME="venubooking"
    DB_USER="root"
    DB_PASS=""
fi

echo "Database: $DB_NAME"
echo "Host: $DB_HOST"
echo ""

# Run migration
echo "Running migration..."
if [ -z "$DB_PASS" ]; then
    mysql -h"$DB_HOST" -u"$DB_USER" "$DB_NAME" < "$MIGRATION_FILE"
else
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MIGRATION_FILE"
fi

if [ $? -eq 0 ]; then
    echo "✓ Migration completed successfully!"
    echo ""
    echo "Quick links settings have been added to the database."
    echo "You can now manage footer quick links from Admin > Settings > Quick Links tab."
else
    echo "✗ Migration failed!"
    exit 1
fi
