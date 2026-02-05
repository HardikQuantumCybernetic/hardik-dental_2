 # VPS Hosting Guide - Dental Management System
 
 ## Complete Step-by-Step Guide for PHP + Oracle Deployment
 
 ---
 
 ## 📋 Prerequisites
 
 - VPS with Ubuntu 22.04 LTS (or similar Linux distro)
 - Minimum 2GB RAM, 20GB Storage
 - Root or sudo access
 - Domain name (optional but recommended)
 
 ---
 
 ## 🔧 Step 1: Initial Server Setup
 
 ```bash
 # Connect to your VPS
 ssh root@your_vps_ip
 
 # Update system packages
 sudo apt update && sudo apt upgrade -y
 
 # Create a non-root user (recommended)
 adduser dental_admin
 usermod -aG sudo dental_admin
 
 # Set up firewall
 sudo ufw allow OpenSSH
 sudo ufw allow 80
 sudo ufw allow 443
 sudo ufw enable
 ```
 
 ---
 
 ## 🌐 Step 2: Install Web Server (Apache/Nginx)
 
 ### Option A: Apache (Recommended for PHP)
 
 ```bash
 # Install Apache
 sudo apt install apache2 -y
 
 # Enable required modules
 sudo a2enmod rewrite
 sudo a2enmod headers
 sudo a2enmod ssl
 
 # Start and enable Apache
 sudo systemctl start apache2
 sudo systemctl enable apache2
 ```
 
 ### Option B: Nginx
 
 ```bash
 # Install Nginx
 sudo apt install nginx -y
 
 # Start and enable Nginx
 sudo systemctl start nginx
 sudo systemctl enable nginx
 ```
 
 ---
 
 ## 🐘 Step 3: Install PHP 8.2
 
 ```bash
 # Add PHP repository
 sudo add-apt-repository ppa:ondrej/php -y
 sudo apt update
 
 # Install PHP and required extensions
 sudo apt install php8.2 php8.2-cli php8.2-fpm php8.2-common \
     php8.2-curl php8.2-mbstring php8.2-xml php8.2-zip \
     php8.2-gd php8.2-bcmath php8.2-intl -y
 
 # Verify installation
 php -v
 ```
 
 ---
 
 ## 🗄️ Step 4: Install Oracle Instant Client & OCI8
 
 ### 4.1 Download Oracle Instant Client
 
 ```bash
 # Create directory for Oracle
 sudo mkdir -p /opt/oracle
 cd /opt/oracle
 
 # Download Oracle Instant Client (from Oracle website)
 # You need to download these files from:
 # https://www.oracle.com/database/technologies/instant-client/linux-x86-64-downloads.html
 
 # Required packages:
 # - instantclient-basic-linux.x64-21.x.0.0.0dbru.zip
 # - instantclient-sdk-linux.x64-21.x.0.0.0dbru.zip
 
 # Upload files to server using SCP:
 # scp instantclient-*.zip root@your_vps_ip:/opt/oracle/
 
 # Unzip the files
 sudo apt install unzip -y
 sudo unzip instantclient-basic-linux.x64-*.zip
 sudo unzip instantclient-sdk-linux.x64-*.zip
 ```
 
 ### 4.2 Configure Oracle Client
 
 ```bash
 # Set environment variables
 echo 'export ORACLE_HOME=/opt/oracle/instantclient_21_x' | sudo tee -a /etc/profile.d/oracle.sh
 echo 'export LD_LIBRARY_PATH=$ORACLE_HOME:$LD_LIBRARY_PATH' | sudo tee -a /etc/profile.d/oracle.sh
 echo 'export PATH=$ORACLE_HOME:$PATH' | sudo tee -a /etc/profile.d/oracle.sh
 
 # Apply changes
 source /etc/profile.d/oracle.sh
 
 # Install libaio (required by Oracle)
 sudo apt install libaio1 -y
 
 # Create symbolic links
 cd /opt/oracle/instantclient_21_x
 sudo ln -s libclntsh.so.21.1 libclntsh.so
 sudo ln -s libocci.so.21.1 libocci.so
 
 # Update library cache
 echo '/opt/oracle/instantclient_21_x' | sudo tee /etc/ld.so.conf.d/oracle-instantclient.conf
 sudo ldconfig
 ```
 
 ### 4.3 Install OCI8 PHP Extension
 
 ```bash
 # Install build dependencies
 sudo apt install php8.2-dev build-essential -y
 
 # Install OCI8 via PECL
 sudo pecl install oci8
 # When prompted, enter: instantclient,/opt/oracle/instantclient_21_x
 
 # Enable OCI8 extension
 echo "extension=oci8.so" | sudo tee /etc/php/8.2/mods-available/oci8.ini
 sudo phpenmod oci8
 
 # Restart PHP-FPM
 sudo systemctl restart php8.2-fpm
 
 # Verify OCI8 is loaded
 php -m | grep oci8
 ```
 
 ---
 
 ## 🗄️ Step 5: Set Up Oracle Database
 
 ### Option A: Remote Oracle Database (Recommended)
 
 If you have an existing Oracle server, configure the connection:
 
 ```bash
 # Create tnsnames.ora
 sudo mkdir -p /opt/oracle/instantclient_21_x/network/admin
 sudo nano /opt/oracle/instantclient_21_x/network/admin/tnsnames.ora
 ```
 
 Add your TNS entry:
 
 ```
 DENTAL_DB =
   (DESCRIPTION =
     (ADDRESS = (PROTOCOL = TCP)(HOST = your_oracle_host)(PORT = 1521))
     (CONNECT_DATA =
       (SERVER = DEDICATED)
       (SERVICE_NAME = ORCL)
     )
   )
 ```
 
 ### Option B: Install Oracle XE on VPS
 
 ```bash
 # Note: Oracle XE requires significant resources (4GB+ RAM)
 # Download Oracle XE from Oracle website
 # https://www.oracle.com/database/technologies/xe-downloads.html
 
 # Install dependencies
 sudo apt install alien libaio1 unixodbc -y
 
 # Convert RPM to DEB and install
 sudo alien --scripts oracle-database-xe-21c-*.rpm
 sudo dpkg -i oracle-database-xe-21c_*.deb
 
 # Configure Oracle XE
 sudo /etc/init.d/oracle-xe-21c configure
 ```
 
 ---
 
 ## 📁 Step 6: Deploy PHP Backend
 
 ### 6.1 Upload Files
 
 ```bash
 # Create web directory
 sudo mkdir -p /var/www/dental-api
 
 # Upload your backend-php folder using SCP
 scp -r backend-php/* root@your_vps_ip:/var/www/dental-api/
 
 # Or use Git
 cd /var/www
 sudo git clone your_repo_url dental-api
 ```
 
 ### 6.2 Configure Permissions
 
 ```bash
 # Set ownership
 sudo chown -R www-data:www-data /var/www/dental-api
 
 # Set permissions
 sudo chmod -R 755 /var/www/dental-api
 
 # Create logs directory
 sudo mkdir -p /var/www/dental-api/logs
 sudo chmod 777 /var/www/dental-api/logs
 ```
 
 ### 6.3 Update Database Configuration
 
 ```bash
 # Edit database config
 sudo nano /var/www/dental-api/config/database.php
 ```
 
 Update with your Oracle credentials:
 
 ```php
 define('DB_HOST', 'your_oracle_host');
 define('DB_PORT', '1521');
 define('DB_SERVICE', 'ORCL');
 define('DB_USERNAME', 'dental_app');
 define('DB_PASSWORD', 'your_secure_password');
 ```
 
 ---
 
 ## 🔒 Step 7: Configure Apache Virtual Host
 
 ```bash
 sudo nano /etc/apache2/sites-available/dental-api.conf
 ```
 
 Add configuration:
 
 ```apache
 <VirtualHost *:80>
     ServerName api.yourdomain.com
     DocumentRoot /var/www/dental-api/api
 
     <Directory /var/www/dental-api/api>
         Options -Indexes +FollowSymLinks
         AllowOverride All
         Require all granted
         
         # CORS Headers
         Header set Access-Control-Allow-Origin "*"
         Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
         Header set Access-Control-Allow-Headers "Content-Type, Authorization"
     </Directory>
 
     # PHP-FPM Configuration
     <FilesMatch \.php$>
         SetHandler "proxy:unix:/run/php/php8.2-fpm.sock|fcgi://localhost"
     </FilesMatch>
 
     ErrorLog ${APACHE_LOG_DIR}/dental-api-error.log
     CustomLog ${APACHE_LOG_DIR}/dental-api-access.log combined
 </VirtualHost>
 ```
 
 Enable the site:
 
 ```bash
 sudo a2ensite dental-api.conf
 sudo a2enmod proxy_fcgi
 sudo systemctl restart apache2
 ```
 
 ---
 
 ## 🔐 Step 8: Install SSL Certificate (HTTPS)
 
 ```bash
 # Install Certbot
 sudo apt install certbot python3-certbot-apache -y
 
 # Get SSL certificate
 sudo certbot --apache -d api.yourdomain.com
 
 # Auto-renewal is configured automatically
 # Test renewal:
 sudo certbot renew --dry-run
 ```
 
 ---
 
 ## 🗃️ Step 9: Initialize Oracle Database
 
 Connect to Oracle and run the schema script:
 
 ```bash
 # Connect using SQL*Plus
 sqlplus dental_app/your_password@//your_oracle_host:1521/ORCL
 
 # Run the schema script
 @/var/www/dental-api/plsql/01_oracle_schema.sql
 ```
 
 Or use SQL Developer to run the scripts.
 
 ---
 
 ## 🌍 Step 10: Deploy React Frontend
 
 ### 10.1 Build the Frontend
 
 On your local machine:
 
 ```bash
 # Update API base URL in your React app
 # Create .env.production file:
 echo "VITE_API_URL=https://api.yourdomain.com" > .env.production
 
 # Build the app
 npm run build
 ```
 
 ### 10.2 Upload to VPS
 
 ```bash
 # Create frontend directory
 sudo mkdir -p /var/www/dental-frontend
 
 # Upload dist folder
 scp -r dist/* root@your_vps_ip:/var/www/dental-frontend/
 
 # Set permissions
 sudo chown -R www-data:www-data /var/www/dental-frontend
 ```
 
 ### 10.3 Configure Frontend Virtual Host
 
 ```bash
 sudo nano /etc/apache2/sites-available/dental-frontend.conf
 ```
 
 ```apache
 <VirtualHost *:80>
     ServerName yourdomain.com
     DocumentRoot /var/www/dental-frontend
 
     <Directory /var/www/dental-frontend>
         Options -Indexes +FollowSymLinks
         AllowOverride All
         Require all granted
     </Directory>
 
     # SPA Routing - redirect all to index.html
     <IfModule mod_rewrite.c>
         RewriteEngine On
         RewriteBase /
         RewriteRule ^index\.html$ - [L]
         RewriteCond %{REQUEST_FILENAME} !-f
         RewriteCond %{REQUEST_FILENAME} !-d
         RewriteRule . /index.html [L]
     </IfModule>
 
     ErrorLog ${APACHE_LOG_DIR}/dental-frontend-error.log
     CustomLog ${APACHE_LOG_DIR}/dental-frontend-access.log combined
 </VirtualHost>
 ```
 
 ```bash
 sudo a2ensite dental-frontend.conf
 sudo a2enmod rewrite
 sudo systemctl restart apache2
 
 # Get SSL for frontend too
 sudo certbot --apache -d yourdomain.com
 ```
 
 ---
 
 ## ✅ Step 11: Verify Installation
 
 ### Test API Endpoints
 
 ```bash
 # Test patients endpoint
 curl -X GET https://api.yourdomain.com/patients.php
 
 # Test auth endpoint
 curl -X POST https://api.yourdomain.com/auth.php?action=signin \
   -H "Content-Type: application/json" \
   -d '{"email":"admin@dental.com","password":"password123"}'
 ```
 
 ### Check Logs
 
 ```bash
 # Apache error logs
 sudo tail -f /var/log/apache2/dental-api-error.log
 
 # PHP-FPM logs
 sudo tail -f /var/log/php8.2-fpm.log
 
 # Application logs
 sudo tail -f /var/www/dental-api/logs/error.log
 ```
 
 ---
 
 ## 🔄 Step 12: Set Up Automatic Deployment (Optional)
 
 Create a deployment script:
 
 ```bash
 sudo nano /var/www/deploy.sh
 ```
 
 ```bash
 #!/bin/bash
 
 echo "🚀 Starting deployment..."
 
 # Pull latest code
 cd /var/www/dental-api
 git pull origin main
 
 # Set permissions
 sudo chown -R www-data:www-data /var/www/dental-api
 
 # Clear PHP opcache
 sudo systemctl reload php8.2-fpm
 
 echo "✅ Deployment complete!"
 ```
 
 ```bash
 chmod +x /var/www/deploy.sh
 ```
 
 ---
 
 ## 📋 Remaining Tasks Checklist
 
 ### Backend Migration (Completed ✅)
 
 - [x] Database.class.php - Oracle connection singleton
 - [x] AuthService.php - PHP authentication replacing Supabase Auth
 - [x] patients.php - Patients API endpoint
 - [x] appointments.php - Appointments API endpoint
 - [x] treatments.php - Treatments API endpoint
 - [x] feedback.php - Feedback API endpoint
 - [x] financials.php - Patient financials API endpoint
 - [x] services.php - Dental services catalog API endpoint
 - [x] doctors.php - Doctors management API endpoint
 - [x] patient-services.php - Patient-service assignments API endpoint
 - [x] auth.php - Authentication API endpoint
 - [x] Oracle PL/SQL schema script
 
 ### Frontend Changes Required ⚠️
 
 You need to update the frontend to call PHP APIs instead of Supabase:
 
 - [ ] Create `src/lib/api.ts` - API client replacing Supabase client
 - [ ] Update `src/hooks/useSupabase.ts` - Replace Supabase hooks
 - [ ] Update authentication components to use PHP auth
 - [ ] Update all components using Supabase queries
 
 ### Database Setup 🗄️
 
 - [ ] Set up Oracle database (local or cloud)
 - [ ] Run `01_oracle_schema.sql` to create tables
 - [ ] Create initial admin user
 - [ ] Migrate existing data from Supabase (if any)
 
 ### Security 🔒
 
 - [ ] Configure HTTPS/SSL
 - [ ] Set secure session settings
 - [ ] Update CORS allowed origins
 - [ ] Implement rate limiting
 - [ ] Set up database backups
 
 ### Testing 🧪
 
 - [ ] Test all API endpoints
 - [ ] Test authentication flow
 - [ ] Test frontend integration
 - [ ] Load testing
 
 ---
 
 ## 🆘 Troubleshooting
 
 ### OCI8 Connection Errors
 
 ```bash
 # Check Oracle client is properly installed
 php -i | grep oci8
 
 # Test connection
 php -r "var_dump(oci_connect('user', 'pass', 'host:1521/service'));"
 ```
 
 ### Permission Issues
 
 ```bash
 # Fix ownership
 sudo chown -R www-data:www-data /var/www/dental-api
 
 # Fix SELinux (if enabled)
 sudo setsebool -P httpd_can_network_connect_db 1
 ```
 
 ### Apache 500 Errors
 
 ```bash
 # Check Apache config
 sudo apache2ctl configtest
 
 # Check PHP errors
 sudo tail -f /var/log/apache2/error.log
 ```
 
 ---
 
 ## 📞 Support
 
 For issues or questions, check:
 - Oracle documentation: https://docs.oracle.com
 - PHP OCI8 documentation: https://www.php.net/manual/en/book.oci8.php
 - Apache documentation: https://httpd.apache.org/docs/