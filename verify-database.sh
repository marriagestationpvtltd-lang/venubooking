#!/bin/bash

# ============================================================================
# Database Verification Script
# ============================================================================
# This script verifies that the database is set up correctly
# Run with: bash verify-database.sh
# ============================================================================

set -e

echo "============================================"
echo "Database Verification"
echo "============================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Default values
DB_NAME="venubooking"
DB_USER="root"
DB_HOST="localhost"

# Read from .env file if exists
if [ -f ".env" ]; then
    if grep -q "DB_NAME=" .env; then
        DB_NAME=$(grep "DB_NAME=" .env | cut -d '=' -f2 | tr -d ' ')
    fi
    if grep -q "DB_USER=" .env; then
        DB_USER=$(grep "DB_USER=" .env | cut -d '=' -f2 | tr -d ' ')
    fi
    if grep -q "DB_HOST=" .env; then
        DB_HOST=$(grep "DB_HOST=" .env | cut -d '=' -f2 | tr -d ' ')
    fi
fi

# Prompt for password
echo "Enter MySQL password for user '$DB_USER':"
read -s DB_PASS
echo ""

# Test connection
echo -n "Testing database connection... "
if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -e "SELECT 1;" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
    echo -e "${RED}Error:${NC} Could not connect to database '$DB_NAME'"
    exit 1
fi

echo ""
echo "Checking Database Structure..."
echo "------------------------------"

# Check tables
EXPECTED_TABLES=18
TABLE_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME';")

echo -n "Tables: "
if [ "$TABLE_COUNT" -eq "$EXPECTED_TABLES" ]; then
    echo -e "${GREEN}✓ $TABLE_COUNT/$EXPECTED_TABLES${NC}"
else
    echo -e "${RED}✗ $TABLE_COUNT/$EXPECTED_TABLES (expected $EXPECTED_TABLES)${NC}"
fi

# List missing tables if any
if [ "$TABLE_COUNT" -lt "$EXPECTED_TABLES" ]; then
    echo ""
    echo "Expected tables:"
    echo "  venues, halls, hall_images, menus, menu_items, hall_menus"
    echo "  additional_services, customers, bookings, booking_menus, booking_services"
    echo "  payment_methods, booking_payment_methods, payments"
    echo "  users, settings, activity_logs, site_images"
fi

echo ""
echo "Checking Sample Data..."
echo "-----------------------"

# Check venues
VENUE_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM venues;")
echo -n "Venues: "
if [ "$VENUE_COUNT" -ge "4" ]; then
    echo -e "${GREEN}✓ $VENUE_COUNT${NC}"
else
    echo -e "${YELLOW}! $VENUE_COUNT (expected at least 4)${NC}"
fi

# Check halls
HALL_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM halls;")
echo -n "Halls: "
if [ "$HALL_COUNT" -ge "8" ]; then
    echo -e "${GREEN}✓ $HALL_COUNT${NC}"
else
    echo -e "${YELLOW}! $HALL_COUNT (expected at least 8)${NC}"
fi

# Check menus
MENU_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM menus;")
echo -n "Menus: "
if [ "$MENU_COUNT" -ge "5" ]; then
    echo -e "${GREEN}✓ $MENU_COUNT${NC}"
else
    echo -e "${YELLOW}! $MENU_COUNT (expected at least 5)${NC}"
fi

# Check services
SERVICE_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM additional_services;")
echo -n "Services: "
if [ "$SERVICE_COUNT" -ge "8" ]; then
    echo -e "${GREEN}✓ $SERVICE_COUNT${NC}"
else
    echo -e "${YELLOW}! $SERVICE_COUNT (expected at least 8)${NC}"
fi

# Check payment methods
PAYMENT_METHOD_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM payment_methods;")
echo -n "Payment Methods: "
if [ "$PAYMENT_METHOD_COUNT" -ge "4" ]; then
    echo -e "${GREEN}✓ $PAYMENT_METHOD_COUNT${NC}"
else
    echo -e "${YELLOW}! $PAYMENT_METHOD_COUNT (expected at least 4)${NC}"
fi

echo ""
echo "Checking Test Bookings..."
echo "-------------------------"

# Check bookings
BOOKING_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM bookings;")
echo -n "Total Bookings: "
if [ "$BOOKING_COUNT" -ge "4" ]; then
    echo -e "${GREEN}✓ $BOOKING_COUNT${NC}"
else
    echo -e "${YELLOW}! $BOOKING_COUNT (expected at least 4)${NC}"
fi

# Check specific bookings
BOOKING_23=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM bookings WHERE id = 23;")
echo -n "Booking #23: "
if [ "$BOOKING_23" -eq "1" ]; then
    echo -e "${GREEN}✓ Found${NC}"
    BOOKING_23_NUMBER=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT booking_number FROM bookings WHERE id = 23;")
    echo "  Number: $BOOKING_23_NUMBER"
else
    echo -e "${RED}✗ Not found${NC}"
fi

BOOKING_37=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM bookings WHERE id = 37;")
echo -n "Booking #37: "
if [ "$BOOKING_37" -eq "1" ]; then
    echo -e "${GREEN}✓ Found${NC}"
    BOOKING_37_NUMBER=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT booking_number FROM bookings WHERE id = 37;")
    echo "  Number: $BOOKING_37_NUMBER"
    
    # Get booking details
    BOOKING_37_MENUS=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM booking_menus WHERE booking_id = 37;")
    BOOKING_37_SERVICES=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM booking_services WHERE booking_id = 37;")
    BOOKING_37_PAYMENTS=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM payments WHERE booking_id = 37;")
    
    echo "  Menus: $BOOKING_37_MENUS"
    echo "  Services: $BOOKING_37_SERVICES"
    echo "  Payments: $BOOKING_37_PAYMENTS"
else
    echo -e "${RED}✗ Not found${NC}"
fi

echo ""
echo "Checking Admin User..."
echo "----------------------"

ADMIN_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM users WHERE username = 'admin';")
echo -n "Admin user: "
if [ "$ADMIN_COUNT" -eq "1" ]; then
    echo -e "${GREEN}✓ Exists${NC}"
    echo "  Username: admin"
    echo "  Default Password: Admin@123"
    echo -e "  ${YELLOW}⚠ Change password after first login!${NC}"
else
    echo -e "${RED}✗ Not found${NC}"
fi

echo ""
echo "Checking Settings..."
echo "--------------------"

SETTINGS_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM settings;")
echo -n "Settings: "
if [ "$SETTINGS_COUNT" -ge "10" ]; then
    echo -e "${GREEN}✓ $SETTINGS_COUNT${NC}"
else
    echo -e "${YELLOW}! $SETTINGS_COUNT (expected at least 10)${NC}"
fi

echo ""
echo "============================================"
echo "Verification Summary"
echo "============================================"
echo ""

# Calculate overall status
ERRORS=0
WARNINGS=0

if [ "$TABLE_COUNT" -lt "$EXPECTED_TABLES" ]; then
    ((ERRORS++))
fi

if [ "$BOOKING_23" -ne "1" ] || [ "$BOOKING_37" -ne "1" ]; then
    ((ERRORS++))
fi

if [ "$ADMIN_COUNT" -ne "1" ]; then
    ((ERRORS++))
fi

if [ "$VENUE_COUNT" -lt "4" ] || [ "$HALL_COUNT" -lt "8" ] || [ "$MENU_COUNT" -lt "5" ]; then
    ((WARNINGS++))
fi

if [ "$ERRORS" -eq "0" ]; then
    echo -e "${GREEN}✓ Database verification passed!${NC}"
    echo ""
    echo "Next Steps:"
    echo "  1. Test admin login: /admin/"
    echo "  2. View booking #37: /admin/bookings/view.php?id=37"
    echo "  3. Change admin password"
    echo "  4. Update payment methods and settings"
    echo ""
    
    if [ "$WARNINGS" -gt "0" ]; then
        echo -e "${YELLOW}Note: Some sample data counts are lower than expected.${NC}"
        echo "This is OK if you've modified the data."
        echo ""
    fi
    
    exit 0
else
    echo -e "${RED}✗ Database verification failed with $ERRORS error(s)${NC}"
    echo ""
    echo "Please run the database setup:"
    echo "  bash setup-database.sh"
    echo ""
    echo "Or manually import:"
    echo "  mysql -u $DB_USER -p < database/complete-database-setup.sql"
    echo ""
    exit 1
fi
