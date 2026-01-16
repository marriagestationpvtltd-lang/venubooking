#!/bin/bash

# ============================================================================
# Venue Booking System - Complete Database Setup Script
# ============================================================================
# This script automates the database setup process
# Run with: bash setup-database.sh
# ============================================================================

set -e  # Exit on any error

echo "============================================"
echo "Venue Booking System - Database Setup"
echo "============================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Default values
DB_NAME="venubooking"
DB_USER="root"
DB_HOST="localhost"

# Check if .env file exists
if [ -f ".env" ]; then
    echo -e "${GREEN}✓${NC} Found .env file, reading configuration..."
    # Read from .env file
    if grep -q "DB_NAME=" .env; then
        DB_NAME=$(grep "DB_NAME=" .env | cut -d '=' -f2 | tr -d ' ')
    fi
    if grep -q "DB_USER=" .env; then
        DB_USER=$(grep "DB_USER=" .env | cut -d '=' -f2 | tr -d ' ')
    fi
    if grep -q "DB_HOST=" .env; then
        DB_HOST=$(grep "DB_HOST=" .env | cut -d '=' -f2 | tr -d ' ')
    fi
else
    echo -e "${YELLOW}!${NC} .env file not found"
    echo -e "${YELLOW}!${NC} Creating .env file from .env.example..."
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo -e "${GREEN}✓${NC} .env file created"
    else
        echo -e "${RED}✗${NC} .env.example not found!"
        exit 1
    fi
fi

echo ""
echo "Database Configuration:"
echo "  Host: $DB_HOST"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo ""

# Prompt for MySQL password
echo "Enter MySQL password for user '$DB_USER':"
read -s DB_PASS
echo ""

# Create a temporary MySQL config file for secure password handling
MYSQL_CNF=$(mktemp)
cat > "$MYSQL_CNF" <<EOF
[client]
user=$DB_USER
password=$DB_PASS
host=$DB_HOST
EOF
chmod 600 "$MYSQL_CNF"

# Test database connection
echo -n "Testing database connection... "
if mysql --defaults-extra-file="$MYSQL_CNF" -e "SELECT 1;" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
    echo -e "${RED}Error:${NC} Could not connect to MySQL with provided credentials"
    rm -f "$MYSQL_CNF"
    exit 1
fi

# Check if SQL file exists
SQL_FILE="database/complete-database-setup.sql"
if [ ! -f "$SQL_FILE" ]; then
    echo -e "${RED}✗${NC} SQL file not found: $SQL_FILE"
    exit 1
fi

echo ""
echo "============================================"
echo "Starting Database Setup..."
echo "============================================"
echo ""

# Import SQL file
echo "Importing database schema and data..."
echo "(This may take a few moments...)"
echo ""

if mysql --defaults-extra-file="$MYSQL_CNF" < "$SQL_FILE"; then
    echo ""
    echo -e "${GREEN}✓${NC} Database setup completed successfully!"
else
    echo ""
    echo -e "${RED}✗${NC} Database setup failed!"
    rm -f "$MYSQL_CNF"
    exit 1
fi

echo ""
echo "============================================"
echo "Verification"
echo "============================================"
echo ""

# Verify tables were created
TABLE_COUNT=$(mysql --defaults-extra-file="$MYSQL_CNF" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME';")
echo -e "Tables created: ${GREEN}$TABLE_COUNT${NC} (expected: 18)"

# Verify bookings
BOOKING_COUNT=$(mysql --defaults-extra-file="$MYSQL_CNF" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM bookings;")
echo -e "Sample bookings: ${GREEN}$BOOKING_COUNT${NC}"

# Check specific test bookings
BOOKING_23=$(mysql --defaults-extra-file="$MYSQL_CNF" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM bookings WHERE id = 23;")
BOOKING_37=$(mysql --defaults-extra-file="$MYSQL_CNF" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM bookings WHERE id = 37;")

if [ "$BOOKING_23" = "1" ]; then
    echo -e "Booking #23: ${GREEN}✓ Found${NC}"
else
    echo -e "Booking #23: ${RED}✗ Not found${NC}"
fi

if [ "$BOOKING_37" = "1" ]; then
    echo -e "Booking #37: ${GREEN}✓ Found${NC}"
else
    echo -e "Booking #37: ${RED}✗ Not found${NC}"
fi

# Verify admin user
ADMIN_COUNT=$(mysql --defaults-extra-file="$MYSQL_CNF" -D"$DB_NAME" -N -e "SELECT COUNT(*) FROM users WHERE username = 'admin';")
if [ "$ADMIN_COUNT" = "1" ]; then
    echo -e "Admin user: ${GREEN}✓ Created${NC}"
else
    echo -e "Admin user: ${RED}✗ Not found${NC}"
fi

# Clean up temporary config file
rm -f "$MYSQL_CNF"

echo ""
echo "============================================"
echo "Setup Summary"
echo "============================================"
echo ""
echo -e "${GREEN}✓${NC} Database: $DB_NAME"
echo -e "${GREEN}✓${NC} Tables: $TABLE_COUNT"
echo -e "${GREEN}✓${NC} Sample Data: Loaded"
echo -e "${GREEN}✓${NC} Admin User: Created"
echo ""
echo "Admin Credentials:"
echo "  URL: http://localhost/venubooking/admin/"
echo "  Username: admin"
echo "  Password: Admin@123"
echo ""
echo -e "${YELLOW}⚠ IMPORTANT:${NC} Change admin password after first login!"
echo ""
echo "Test Booking URLs:"
echo "  - http://localhost/venubooking/admin/bookings/view.php?id=23"
echo "  - http://localhost/venubooking/admin/bookings/view.php?id=37"
echo ""
echo "============================================"
echo -e "${GREEN}✓ Setup Complete!${NC}"
echo "============================================"
echo ""
echo "Next Steps:"
echo "  1. Update .env file with production database password"
echo "  2. Access admin panel and change default password"
echo "  3. Configure payment methods in Admin > Payment Methods"
echo "  4. Update company settings in Admin > Settings"
echo ""
echo "For detailed instructions, see: DATABASE_INSTALLATION_GUIDE.md"
echo ""
