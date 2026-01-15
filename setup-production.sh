#!/bin/bash
#
# Quick Production Setup Script
# Run this after deploying to production to configure the environment
#

echo "=========================================="
echo "Venue Booking System - Production Setup"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Note: Some operations may require sudo privileges"
    SUDO="sudo"
else
    SUDO=""
fi

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo "This script will help you set up the production environment."
echo ""

# Step 1: Check if .env exists
echo "Step 1: Environment Configuration"
echo "----------------------------------"
if [ ! -f ".env" ]; then
    echo -e "${YELLOW}Creating .env from .env.example...${NC}"
    cp .env.example .env
    echo -e "${GREEN}✓ .env created${NC}"
    echo ""
    echo "Please edit .env with your database credentials:"
    echo "  nano .env"
    echo ""
    read -p "Press Enter after editing .env..."
else
    echo -e "${GREEN}✓ .env already exists${NC}"
fi
echo ""

# Step 2: Directory permissions
echo "Step 2: Setting Directory Permissions"
echo "--------------------------------------"

# Uploads directory
if [ -d "uploads" ]; then
    $SUDO chmod 775 uploads/
    $SUDO chmod 775 uploads/*/ 2>/dev/null
    echo -e "${GREEN}✓ uploads/ permissions set${NC}"
else
    echo -e "${RED}✗ uploads/ directory not found${NC}"
fi

# Logs directory
if [ ! -d "logs" ]; then
    mkdir -p logs
    echo -e "${GREEN}✓ logs/ directory created${NC}"
fi
$SUDO chmod 775 logs/
echo -e "${GREEN}✓ logs/ permissions set${NC}"

# .env permissions
if [ -f ".env" ]; then
    chmod 600 .env
    echo -e "${GREEN}✓ .env permissions set (600)${NC}"
fi

echo ""

# Step 3: Database setup
echo "Step 3: Database Setup"
echo "----------------------"
echo "Would you like to set up the database now?"
read -p "Enter MySQL root password (or press Enter to skip): " MYSQL_PASSWORD

if [ ! -z "$MYSQL_PASSWORD" ]; then
    # Load database credentials from .env
    source .env 2>/dev/null || echo "Warning: Could not load .env"
    
    echo "Creating database..."
    mysql -u root -p"$MYSQL_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Database created${NC}"
        
        echo "Importing schema..."
        if [ -f "database/complete-setup.sql" ]; then
            mysql -u root -p"$MYSQL_PASSWORD" "$DB_NAME" < database/complete-setup.sql 2>/dev/null
            if [ $? -eq 0 ]; then
                echo -e "${GREEN}✓ Schema imported${NC}"
            else
                echo -e "${RED}✗ Schema import failed${NC}"
            fi
        else
            echo -e "${YELLOW}⚠ database/complete-setup.sql not found${NC}"
        fi
    else
        echo -e "${RED}✗ Database creation failed${NC}"
    fi
else
    echo -e "${YELLOW}⚠ Database setup skipped${NC}"
fi
echo ""

# Step 4: Web server configuration
echo "Step 4: Web Server Configuration"
echo "---------------------------------"
echo "Which web server are you using?"
echo "1) Apache"
echo "2) Nginx"
echo "3) Skip"
read -p "Enter choice [1-3]: " WEBSERVER_CHOICE

case $WEBSERVER_CHOICE in
    1)
        echo ""
        echo "Apache Configuration:"
        echo "--------------------"
        echo "1. Ensure mod_rewrite is enabled:"
        echo "   sudo a2enmod rewrite"
        echo ""
        echo "2. Ensure .htaccess is allowed (check Apache config):"
        echo "   <Directory /var/www/html>"
        echo "       AllowOverride All"
        echo "   </Directory>"
        echo ""
        echo "3. Restart Apache:"
        echo "   sudo systemctl restart apache2"
        echo ""
        ;;
    2)
        echo ""
        echo "Nginx Configuration:"
        echo "--------------------"
        echo "Add this to your server block in /etc/nginx/sites-available/default:"
        echo ""
        echo "location / {"
        echo "    try_files \$uri \$uri/ /index.php?\$query_string;"
        echo "}"
        echo ""
        echo "location ~ \.php$ {"
        echo "    fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;"
        echo "    fastcgi_index index.php;"
        echo "    fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;"
        echo "    include fastcgi_params;"
        echo "}"
        echo ""
        echo "Then restart Nginx:"
        echo "   sudo systemctl restart nginx"
        echo ""
        ;;
    *)
        echo -e "${YELLOW}⚠ Web server configuration skipped${NC}"
        ;;
esac

read -p "Press Enter to continue..."
echo ""

# Step 5: Validation
echo "Step 5: Running Production Validation"
echo "--------------------------------------"
if [ -f "validate-production.sh" ]; then
    chmod +x validate-production.sh
    ./validate-production.sh
else
    echo -e "${YELLOW}⚠ validate-production.sh not found${NC}"
fi
echo ""

# Step 6: Final reminders
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo -e "${GREEN}Next Steps:${NC}"
echo ""
echo "1. Access your site: http://yourdomain.com/"
echo "2. Login to admin: http://yourdomain.com/admin/"
echo "   Default credentials: admin / Admin@123"
echo "   ${RED}CHANGE PASSWORD IMMEDIATELY!${NC}"
echo ""
echo "3. Configure settings in Admin Panel:"
echo "   - Site name, logo, favicon"
echo "   - Currency and tax rate"
echo "   - Email settings (SMTP)"
echo "   - Contact information"
echo ""
echo "4. Test the booking flow"
echo "5. Test email notifications"
echo "6. Review PRODUCTION_DEPLOYMENT_GUIDE.md for more details"
echo ""
echo -e "${YELLOW}Security Reminders:${NC}"
echo "- Enable HTTPS/SSL in production"
echo "- Set up regular backups"
echo "- Monitor error logs: logs/error.log"
echo "- Keep software updated"
echo ""
echo "=========================================="
