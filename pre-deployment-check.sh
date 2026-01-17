#!/bin/bash
# Pre-Deployment Validation Script
# Run this before deploying to production

echo "================================================"
echo "  Venue Booking System - Pre-Deployment Check"
echo "================================================"
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
CHECKS=0
PASSED=0
FAILED=0
WARNINGS=0

# Function to print status
check_pass() {
    echo -e "${GREEN}✓${NC} $1"
    ((CHECKS++))
    ((PASSED++))
}

check_fail() {
    echo -e "${RED}✗${NC} $1"
    ((CHECKS++))
    ((FAILED++))
}

check_warn() {
    echo -e "${YELLOW}⚠${NC} $1"
    ((CHECKS++))
    ((WARNINGS++))
}

echo "Running pre-deployment checks..."
echo ""

# ==============================================
# 1. FILE STRUCTURE CHECKS
# ==============================================
echo -e "${BLUE}[1/8] Checking File Structure...${NC}"

if [ -f "index.php" ]; then
    check_pass "index.php exists"
else
    check_fail "index.php not found"
fi

if [ -d "admin" ]; then
    check_pass "admin directory exists"
else
    check_fail "admin directory not found"
fi

if [ -d "includes" ]; then
    check_pass "includes directory exists"
else
    check_fail "includes directory not found"
fi

if [ -d "uploads" ]; then
    check_pass "uploads directory exists"
    
    # Check write permissions
    if [ -w "uploads" ]; then
        check_pass "uploads directory is writable"
    else
        check_fail "uploads directory is NOT writable (chmod 755 required)"
    fi
else
    check_fail "uploads directory not found (mkdir uploads required)"
fi

if [ -d "css" ] && [ -d "js" ]; then
    check_pass "CSS and JS directories exist"
else
    check_warn "CSS or JS directory missing"
fi

echo ""

# ==============================================
# 2. CONFIGURATION FILES
# ==============================================
echo -e "${BLUE}[2/8] Checking Configuration Files...${NC}"

if [ -f ".env" ]; then
    check_pass ".env file exists"
    
    # Check if .env contains required variables
    if grep -q "DB_HOST" .env; then
        check_pass ".env contains DB_HOST"
    else
        check_warn ".env missing DB_HOST variable"
    fi
    
    if grep -q "DB_NAME" .env; then
        check_pass ".env contains DB_NAME"
    else
        check_warn ".env missing DB_NAME variable"
    fi
else
    check_warn ".env file not found (copy from .env.example)"
fi

if [ -f "config/database.php" ]; then
    check_pass "config/database.php exists"
else
    check_fail "config/database.php not found"
fi

if [ -f ".env.example" ]; then
    check_pass ".env.example template exists"
else
    check_warn ".env.example template not found"
fi

echo ""

# ==============================================
# 3. DATABASE FILES
# ==============================================
echo -e "${BLUE}[3/8] Checking Database Files...${NC}"

if [ -d "database" ]; then
    check_pass "database directory exists"
    
    if [ -f "database/schema.sql" ]; then
        check_pass "database/schema.sql exists"
    else
        check_warn "database/schema.sql not found"
    fi
    
    if [ -f "database/complete-setup.sql" ]; then
        check_pass "database/complete-setup.sql exists"
    else
        check_warn "database/complete-setup.sql not found"
    fi
else
    check_fail "database directory not found"
fi

echo ""

# ==============================================
# 4. REQUIRED PHP FILES
# ==============================================
echo -e "${BLUE}[4/8] Checking Required PHP Files...${NC}"

required_files=(
    "includes/db.php"
    "includes/functions.php"
    "includes/header.php"
    "includes/footer.php"
    "booking-step2.php"
    "booking-step3.php"
    "booking-step4.php"
    "booking-step5.php"
    "confirmation.php"
    "admin/index.php"
    "admin/bookings/index.php"
    "admin/bookings/view.php"
)

missing_files=0
for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        ((PASSED++))
    else
        check_fail "Missing: $file"
        ((missing_files++))
    fi
    ((CHECKS++))
done

if [ $missing_files -eq 0 ]; then
    check_pass "All required PHP files present (${#required_files[@]} files)"
fi

echo ""

# ==============================================
# 5. PHP SYNTAX CHECK
# ==============================================
echo -e "${BLUE}[5/8] Checking PHP Syntax...${NC}"

php_errors=0
if command -v php &> /dev/null; then
    check_pass "PHP CLI available"
    
    # Find all PHP files and check syntax
    echo "   Checking PHP files for syntax errors..."
    while IFS= read -r -d '' file; do
        if ! php -l "$file" &> /dev/null; then
            check_fail "Syntax error in: $file"
            ((php_errors++))
        fi
    done < <(find . -name "*.php" -not -path "./vendor/*" -print0)
    
    if [ $php_errors -eq 0 ]; then
        check_pass "No PHP syntax errors found"
    else
        check_fail "Found $php_errors PHP files with syntax errors"
    fi
else
    check_warn "PHP CLI not available - skipping syntax check"
fi

echo ""

# ==============================================
# 6. JAVASCRIPT FILES
# ==============================================
echo -e "${BLUE}[6/8] Checking JavaScript Files...${NC}"

required_js=(
    "js/main.js"
    "js/booking-flow.js"
    "js/nepali-date-picker.js"
)

missing_js=0
for js_file in "${required_js[@]}"; do
    if [ -f "$js_file" ]; then
        check_pass "Found: $js_file"
    else
        check_fail "Missing: $js_file"
        ((missing_js++))
    fi
done

echo ""

# ==============================================
# 7. SECURITY CHECKS
# ==============================================
echo -e "${BLUE}[7/8] Checking Security...${NC}"

# Check if .env is in .gitignore
if [ -f ".gitignore" ]; then
    if grep -q "\.env" .gitignore; then
        check_pass ".env file in .gitignore"
    else
        check_warn ".env should be added to .gitignore"
    fi
else
    check_warn ".gitignore file not found"
fi

# Check for test files that should be removed
test_files=(
    "test-settings.html"
    "test-services-display.php"
    "validate-settings.php"
)

test_files_found=0
for test_file in "${test_files[@]}"; do
    if [ -f "$test_file" ]; then
        check_warn "Test file should be removed before production: $test_file"
        ((test_files_found++))
    fi
done

if [ $test_files_found -eq 0 ]; then
    check_pass "No test files found in production directory"
fi

# Check uploads directory security
if [ -f "uploads/.htaccess" ] || [ -f "uploads/index.php" ]; then
    check_pass "uploads directory has security file"
else
    check_warn "Consider adding .htaccess or index.php to uploads directory"
fi

echo ""

# ==============================================
# 8. DOCUMENTATION
# ==============================================
echo -e "${BLUE}[8/8] Checking Documentation...${NC}"

doc_files=(
    "README.md"
    "INSTALLATION.md"
    "SYSTEM_AUDIT_TESTING_GUIDE.md"
)

for doc_file in "${doc_files[@]}"; do
    if [ -f "$doc_file" ]; then
        check_pass "Documentation exists: $doc_file"
    else
        check_warn "Documentation missing: $doc_file"
    fi
done

echo ""
echo "================================================"
echo "  CHECK SUMMARY"
echo "================================================"
echo ""
echo "Total Checks: $CHECKS"
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo -e "${YELLOW}Warnings: $WARNINGS${NC}"
echo ""

# Calculate percentage
if [ $CHECKS -gt 0 ]; then
    PERCENTAGE=$((PASSED * 100 / CHECKS))
    echo "Pass Rate: $PERCENTAGE%"
    echo ""
fi

# Final recommendation
if [ $FAILED -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}✅ ALL CHECKS PASSED!${NC}"
    echo "System appears ready for production deployment."
    echo ""
    echo "Next steps:"
    echo "1. Run: php test-system-validation.php"
    echo "2. Follow: SYSTEM_AUDIT_TESTING_GUIDE.md"
    echo "3. Complete manual testing on staging environment"
    exit 0
elif [ $FAILED -eq 0 ]; then
    echo -e "${YELLOW}⚠ CHECKS PASSED WITH WARNINGS${NC}"
    echo "Please review warnings before deployment."
    echo ""
    echo "Next steps:"
    echo "1. Address warnings if possible"
    echo "2. Run: php test-system-validation.php"
    echo "3. Follow: SYSTEM_AUDIT_TESTING_GUIDE.md"
    exit 0
else
    echo -e "${RED}❌ SOME CHECKS FAILED${NC}"
    echo "Please fix all failed checks before deployment."
    echo ""
    echo "Review the errors above and:"
    echo "1. Fix missing files or directories"
    echo "2. Correct PHP syntax errors"
    echo "3. Set proper file permissions"
    echo "4. Configure .env file"
    exit 1
fi
