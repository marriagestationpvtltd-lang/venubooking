#!/bin/bash

# Email Notification System Setup Script
# This script helps set up the email notification system

echo "=========================================="
echo "Email Notification System Setup"
echo "=========================================="
echo ""

# Check if we're in the right directory
if [ ! -f "config/database.php" ]; then
    echo "Error: Please run this script from the project root directory"
    echo "(The directory containing the 'config' folder)"
    exit 1
fi

echo "Step 1: Checking database connection..."
echo ""

# Load database credentials from .env if it exists
if [ -f ".env" ]; then
    echo "Loading database credentials from .env file..."
    # Safely parse .env file without executing code
    while IFS='=' read -r key value; do
        # Skip comments and empty lines
        [[ "$key" =~ ^#.*$ ]] && continue
        [[ -z "$key" ]] && continue
        # Remove quotes from value if present
        value="${value%\"}"
        value="${value#\"}"
        value="${value%\'}"
        value="${value#\'}"
        # Export the variable
        export "$key=$value"
    done < <(grep -v '^[[:space:]]*#' .env | grep -v '^[[:space:]]*$')
else
    echo "Warning: .env file not found. Using default credentials."
    DB_HOST=${DB_HOST:-localhost}
    DB_NAME=${DB_NAME:-venubooking}
    DB_USER=${DB_USER:-root}
    DB_PASS=${DB_PASS:-}
fi

echo "Database: $DB_NAME"
echo "Host: $DB_HOST"
echo "User: $DB_USER"
echo ""

# Test database connection
echo "Testing database connection..."
if [ -z "$DB_PASS" ]; then
    mysql -h"$DB_HOST" -u"$DB_USER" -e "USE $DB_NAME" 2>/dev/null
else
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME" 2>/dev/null
fi

if [ $? -eq 0 ]; then
    echo "✓ Database connection successful"
else
    echo "✗ Database connection failed. Please check your credentials."
    exit 1
fi

echo ""
echo "Step 2: Applying email settings migration..."
echo ""

# Apply migration
if [ -z "$DB_PASS" ]; then
    mysql -h"$DB_HOST" -u"$DB_USER" "$DB_NAME" < database/migrations/add_email_settings.sql
else
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migrations/add_email_settings.sql
fi

if [ $? -eq 0 ]; then
    echo "✓ Email settings migration applied successfully"
else
    echo "✗ Migration failed. This might be normal if settings already exist."
fi

echo ""
echo "Step 3: Verifying email settings..."
echo ""

# Check if settings were created
if [ -z "$DB_PASS" ]; then
    SETTING_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USER" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM settings WHERE setting_key LIKE 'email_%' OR setting_key LIKE 'smtp_%' OR setting_key = 'admin_email'")
else
    SETTING_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM settings WHERE setting_key LIKE 'email_%' OR setting_key LIKE 'smtp_%' OR setting_key = 'admin_email'")
fi

echo "Email settings in database: $SETTING_COUNT"

if [ "$SETTING_COUNT" -ge 8 ]; then
    echo "✓ Email settings configured"
else
    echo "⚠ Some email settings may be missing"
fi

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "Next Steps:"
echo "1. Open your admin panel: http://your-domain/admin/"
echo "2. Go to Settings → Email Settings"
echo "3. Configure your email preferences:"
echo "   - Set admin email address"
echo "   - Optionally configure SMTP for better deliverability"
echo "4. Test by creating a new booking"
echo ""
echo "For detailed configuration guide, see:"
echo "EMAIL_NOTIFICATION_GUIDE.md"
echo ""
