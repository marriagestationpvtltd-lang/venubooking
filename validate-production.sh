#!/bin/bash
#
# Production Readiness Validation Script
# This script validates that all production requirements are met
#

echo "=========================================="
echo "Production Readiness Validation"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
PASSED=0
FAILED=0
WARNINGS=0

# Function to print test results
print_result() {
    local test_name="$1"
    local result="$2"
    local message="$3"
    
    if [ "$result" = "PASS" ]; then
        echo -e "${GREEN}✓${NC} $test_name"
        ((PASSED++))
    elif [ "$result" = "WARN" ]; then
        echo -e "${YELLOW}⚠${NC} $test_name - $message"
        ((WARNINGS++))
    else
        echo -e "${RED}✗${NC} $test_name - $message"
        ((FAILED++))
    fi
}

echo "1. FILE STRUCTURE CHECKS"
echo "------------------------"

# Check required files exist
if [ -f "config/database.php" ]; then
    print_result "Database config exists" "PASS"
else
    print_result "Database config exists" "FAIL" "config/database.php not found"
fi

if [ -f "config/production.php" ]; then
    print_result "Production config exists" "PASS"
else
    print_result "Production config exists" "WARN" "config/production.php not found (optional)"
fi

if [ -f ".env.example" ]; then
    print_result ".env.example exists" "PASS"
else
    print_result ".env.example exists" "FAIL" ".env.example not found"
fi

# Check critical directories
if [ -d "uploads" ]; then
    print_result "Uploads directory exists" "PASS"
    
    # Check if writable
    if [ -w "uploads" ]; then
        print_result "Uploads directory writable" "PASS"
    else
        print_result "Uploads directory writable" "FAIL" "uploads/ is not writable"
    fi
else
    print_result "Uploads directory exists" "FAIL" "uploads/ directory not found"
fi

echo ""
echo "2. SECURITY CHECKS"
echo "------------------"

# Check for debug code
if grep -r "var_dump\|print_r\|var_export" --include="*.php" . | grep -v ".git" | grep -v "vendor" > /dev/null 2>&1; then
    print_result "No debug functions" "WARN" "Found var_dump/print_r in code"
else
    print_result "No debug functions" "PASS"
fi

# Check for test files
if ls test*.php test*.html 2>/dev/null | grep -q .; then
    print_result "No test files" "WARN" "Test files found in root"
else
    print_result "No test files" "PASS"
fi

# Check .env is in .gitignore
if [ -f ".gitignore" ]; then
    if grep -q "^\.env$" .gitignore; then
        print_result ".env in .gitignore" "PASS"
    else
        print_result ".env in .gitignore" "FAIL" ".env should be in .gitignore"
    fi
fi

# Check file permissions (if on Unix)
if [ -f ".env" ]; then
    PERMS=$(stat -c "%a" .env 2>/dev/null || stat -f "%OLp" .env 2>/dev/null)
    if [ "$PERMS" = "600" ] || [ "$PERMS" = "400" ]; then
        print_result ".env file permissions" "PASS"
    else
        print_result ".env file permissions" "WARN" ".env should be 600 or 400 (currently $PERMS)"
    fi
fi

echo ""
echo "3. PHP SYNTAX CHECKS"
echo "--------------------"

# Check PHP files for syntax errors
PHP_FILES=$(find . -name "*.php" -not -path "./vendor/*" -not -path "./.git/*")
SYNTAX_ERRORS=0

for file in $PHP_FILES; do
    php -l "$file" > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo -e "${RED}✗${NC} Syntax error in: $file"
        ((SYNTAX_ERRORS++))
    fi
done

if [ $SYNTAX_ERRORS -eq 0 ]; then
    print_result "PHP syntax validation" "PASS"
else
    print_result "PHP syntax validation" "FAIL" "Found $SYNTAX_ERRORS files with syntax errors"
fi

echo ""
echo "4. DATABASE CHECKS"
echo "------------------"

# Check if database schema file exists
if [ -f "database/schema.sql" ] || [ -f "database/complete-setup.sql" ]; then
    print_result "Database schema exists" "PASS"
else
    print_result "Database schema exists" "FAIL" "No schema.sql or complete-setup.sql found"
fi

echo ""
echo "5. REQUIRED FUNCTIONS CHECK"
echo "---------------------------"

# Check critical functions exist
if grep -q "function getSetting" includes/functions.php 2>/dev/null; then
    print_result "getSetting() function exists" "PASS"
else
    print_result "getSetting() function exists" "FAIL" "getSetting() not found"
fi

if grep -q "function sendEmail" includes/functions.php 2>/dev/null; then
    print_result "sendEmail() function exists" "PASS"
else
    print_result "sendEmail() function exists" "FAIL" "sendEmail() not found"
fi

if grep -q "function createBooking" includes/functions.php 2>/dev/null; then
    print_result "createBooking() function exists" "PASS"
else
    print_result "createBooking() function exists" "FAIL" "createBooking() not found"
fi

echo ""
echo "6. FRONTEND CHECKS"
echo "------------------"

# Check JavaScript files
if [ -f "js/main.js" ]; then
    print_result "main.js exists" "PASS"
else
    print_result "main.js exists" "FAIL" "js/main.js not found"
fi

if [ -f "js/booking-flow.js" ]; then
    print_result "booking-flow.js exists" "PASS"
else
    print_result "booking-flow.js exists" "FAIL" "js/booking-flow.js not found"
fi

# Check CSS files
if [ -f "css/style.css" ] && [ -f "css/booking.css" ] && [ -f "css/responsive.css" ]; then
    print_result "CSS files exist" "PASS"
else
    print_result "CSS files exist" "FAIL" "Missing required CSS files"
fi

echo ""
echo "7. DOCUMENTATION CHECKS"
echo "-----------------------"

if [ -f "README.md" ]; then
    print_result "README.md exists" "PASS"
else
    print_result "README.md exists" "WARN" "README.md not found"
fi

if [ -f "INSTALLATION.md" ] || [ -f "PRODUCTION_DEPLOYMENT_GUIDE.md" ]; then
    print_result "Installation guide exists" "PASS"
else
    print_result "Installation guide exists" "WARN" "No installation documentation found"
fi

echo ""
echo "8. CODE QUALITY CHECKS"
echo "----------------------"

# Check for hardcoded credentials (common patterns)
# Note: This is a simple pattern check, not exhaustive
if grep -rE "password.*=.*['\"][^'\"$]+['\"]" --include="*.php" . 2>/dev/null | grep -v ".git" | grep -v "vendor" | grep -v "example" | grep -v "sample" | grep -v "getSetting" | grep -v "ENV" > /dev/null; then
    print_result "No hardcoded passwords" "WARN" "Possible hardcoded credentials found"
else
    print_result "No hardcoded passwords" "PASS"
fi

# Check for TODO/FIXME comments
TODO_COUNT=$(grep -rE "TODO|FIXME" --include="*.php" --include="*.js" . 2>/dev/null | grep -v ".git" | wc -l)
if [ $TODO_COUNT -gt 0 ]; then
    print_result "No TODO/FIXME comments" "WARN" "Found $TODO_COUNT TODO/FIXME comments"
else
    print_result "No TODO/FIXME comments" "PASS"
fi

echo ""
echo "=========================================="
echo "SUMMARY"
echo "=========================================="
echo -e "${GREEN}Passed:${NC}   $PASSED"
echo -e "${YELLOW}Warnings:${NC} $WARNINGS"
echo -e "${RED}Failed:${NC}   $FAILED"
echo ""

if [ $FAILED -eq 0 ]; then
    if [ $WARNINGS -eq 0 ]; then
        echo -e "${GREEN}✓ ALL CHECKS PASSED - READY FOR PRODUCTION${NC}"
        exit 0
    else
        echo -e "${YELLOW}⚠ CHECKS PASSED WITH WARNINGS - REVIEW WARNINGS BEFORE PRODUCTION${NC}"
        exit 0
    fi
else
    echo -e "${RED}✗ SOME CHECKS FAILED - FIX ISSUES BEFORE PRODUCTION${NC}"
    exit 1
fi
