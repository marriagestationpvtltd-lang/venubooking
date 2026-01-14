#!/bin/bash
# Installation script for Image Upload Feature

echo "========================================"
echo "Image Upload Feature - Installation"
echo "========================================"
echo ""

# Check if running from correct directory
if [ ! -f "database/migrations/add_site_images_table.sql" ]; then
    echo "Error: Please run this script from the project root directory"
    exit 1
fi

echo "Step 1: Creating database table..."
echo ""

# Load environment variables
if [ -f ".env" ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo "Warning: .env file not found. Using default values."
    DB_HOST=${DB_HOST:-localhost}
    DB_NAME=${DB_NAME:-venubooking}
    DB_USER=${DB_USER:-root}
    DB_PASS=${DB_PASS:-}
fi

# Run migration
if [ -z "$DB_PASS" ]; then
    mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" < database/migrations/add_site_images_table.sql
else
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migrations/add_site_images_table.sql
fi

if [ $? -eq 0 ]; then
    echo "✓ Database table created successfully"
else
    echo "✗ Failed to create database table"
    echo "  You may need to run the migration manually:"
    echo "  mysql -u $DB_USER -p $DB_NAME < database/migrations/add_site_images_table.sql"
    exit 1
fi

echo ""
echo "Step 2: Setting up uploads directory..."

# Create uploads directory if it doesn't exist
if [ ! -d "uploads" ]; then
    mkdir -p uploads
    echo "✓ Created uploads directory"
else
    echo "✓ Uploads directory already exists"
fi

# Set permissions
chmod 755 uploads
echo "✓ Set directory permissions (755)"

# Create .gitkeep if it doesn't exist
if [ ! -f "uploads/.gitkeep" ]; then
    touch uploads/.gitkeep
    echo "✓ Created .gitkeep file"
fi

echo ""
echo "========================================"
echo "Installation Complete!"
echo "========================================"
echo ""
echo "Next steps:"
echo "1. Log in to the admin panel"
echo "2. Navigate to 'Images' in the sidebar"
echo "3. Upload your first image"
echo ""
echo "For detailed usage instructions, see IMAGE_UPLOAD_GUIDE.md"
echo ""
