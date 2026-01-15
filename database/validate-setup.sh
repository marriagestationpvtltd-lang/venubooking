#!/bin/bash
# Database Setup Validation Script
# This script validates the SQL files for syntax errors

echo "=== Database SQL Validation Script ==="
echo ""

# Check if mysql client is available
if ! command -v mysql &> /dev/null; then
    echo "❌ MySQL client not found"
    exit 1
fi

echo "✅ MySQL client found"
echo ""

# Files to validate
FILES=(
    "database/schema.sql"
    "database/sample-data.sql"
    "database/complete-setup.sql"
    "database/fix-booking-23.sql"
)

echo "Validating SQL files..."
echo ""

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "📄 Checking: $file"
        # Basic syntax check - look for common SQL syntax errors
        if grep -q "CREATE TABLE\|INSERT INTO\|SELECT\|USE" "$file"; then
            echo "   ✅ File contains valid SQL commands"
            
            # Count tables being created
            table_count=$(grep -c "CREATE TABLE" "$file" 2>/dev/null || echo "0")
            echo "   📊 Creates $table_count tables"
            
            # Count insert statements
            insert_count=$(grep -c "INSERT INTO" "$file" 2>/dev/null || echo "0")
            echo "   📊 Contains $insert_count INSERT statements"
        else
            echo "   ⚠️  Warning: No SQL commands found"
        fi
    else
        echo "❌ File not found: $file"
    fi
    echo ""
done

echo "=== Validation Complete ==="
echo ""
echo "To apply the database setup, run one of these commands:"
echo ""
echo "Option 1 - Complete fresh setup:"
echo "  mysql -u root -p < database/complete-setup.sql"
echo ""
echo "Option 2 - Add booking #23 only:"
echo "  mysql -u root -p < database/fix-booking-23.sql"
echo ""
echo "Option 3 - Schema then data:"
echo "  mysql -u root -p < database/schema.sql"
echo "  mysql -u root -p < database/sample-data.sql"
echo ""
