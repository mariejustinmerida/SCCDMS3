# Production Deployment Guide
## Saint Columban College Document Management System

### 🚨 **CRITICAL: Pre-Deployment Checklist**

#### **1. Security Configuration**
- [ ] Change default database credentials
- [ ] Set up environment variables (copy `config/production.env.example` to `.env`)
- [ ] Configure SSL certificate for HTTPS
- [ ] Set up proper file permissions (755 for directories, 644 for files)
- [ ] Configure firewall rules
- [ ] Set up regular security updates

#### **2. Database Setup**
```sql
-- Create production database
CREATE DATABASE scc_dms_production CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Create dedicated database user
CREATE USER 'scc_dms_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON scc_dms_production.* TO 'scc_dms_user'@'localhost';
FLUSH PRIVILEGES;
```

#### **3. File System Setup**
```bash
# Create storage directories with proper permissions
mkdir -p storage/uploads storage/documents storage/temp storage/backups
chmod 755 storage storage/uploads storage/documents storage/temp storage/backups
chmod 644 storage/.htaccess

# Create logs directory
mkdir -p logs
chmod 755 logs
```

#### **4. Web Server Configuration**

**Apache (.htaccess)**
```apache
# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Prevent access to sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Cache static files
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
</IfModule>
```

**Nginx Configuration**
```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    root /var/www/scc_dms;
    index index.php;
    
    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    
    # Prevent access to sensitive files
    location ~ /\.env {
        deny all;
    }
    
    location ~ \.log$ {
        deny all;
    }
    
    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    
    # Static file caching
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
    }
}
```

### **5. Performance Optimization**

#### **Database Optimization**
```sql
-- Create indexes for better performance
CREATE INDEX idx_documents_status ON documents(status);
CREATE INDEX idx_documents_creator ON documents(creator_id);
CREATE INDEX idx_documents_created_at ON documents(created_at);
CREATE INDEX idx_document_workflow_doc_office ON document_workflow(document_id, office_id);
CREATE INDEX idx_document_workflow_status ON document_workflow(status);
CREATE INDEX idx_document_logs_doc_id ON document_logs(document_id);
CREATE INDEX idx_document_logs_user_id ON document_logs(user_id);
CREATE INDEX idx_users_office_id ON users(office_id);
CREATE INDEX idx_users_role_id ON users(role_id);
```

#### **PHP Configuration (php.ini)**
```ini
; Memory and execution limits
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

; File upload settings
upload_max_filesize = 10M
post_max_size = 12M
max_file_uploads = 20

; Session settings
session.gc_maxlifetime = 28800
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1

; Error reporting (production)
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; OPcache for better performance
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
```

### **6. Monitoring and Maintenance**

#### **Log Monitoring**
```bash
# Monitor system logs
tail -f logs/system_$(date +%Y-%m-%d).log

# Monitor user actions
tail -f logs/user_actions_$(date +%Y-%m-%d).log

# Monitor security events
tail -f logs/security_$(date +%Y-%m-%d).log
```

#### **Database Backup Script**
```bash
#!/bin/bash
# backup_database.sh

DB_NAME="scc_dms_production"
DB_USER="scc_dms_user"
DB_PASS="secure_password_here"
BACKUP_DIR="/var/backups/scc_dms"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Create database backup
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/db_backup_$DATE.sql

# Compress backup
gzip $BACKUP_DIR/db_backup_$DATE.sql

# Keep only last 7 days of backups
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +7 -delete

echo "Database backup completed: db_backup_$DATE.sql.gz"
```

#### **File Backup Script**
```bash
#!/bin/bash
# backup_files.sh

SOURCE_DIR="/var/www/scc_dms/storage"
BACKUP_DIR="/var/backups/scc_dms/files"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Create file backup
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz -C $SOURCE_DIR .

# Keep only last 7 days of backups
find $BACKUP_DIR -name "files_backup_*.tar.gz" -mtime +7 -delete

echo "File backup completed: files_backup_$DATE.tar.gz"
```

### **7. User Management for Multiple Offices**

#### **Office Setup**
1. **Create Office Records**: Add all offices to the `offices` table
2. **Create User Roles**: Set up appropriate roles in the `roles` table
3. **Create User Accounts**: Add users for each office with proper role assignments

#### **Sample Office Data**
```sql
INSERT INTO offices (office_name, office_code, description) VALUES
('Office of the President', 'PRES', 'Main administrative office'),
('Office of Academic Affairs', 'ACAD', 'Academic administration'),
('Office of Student Affairs', 'STUD', 'Student services'),
('Office of Finance', 'FIN', 'Financial management'),
('Office of Human Resources', 'HR', 'Human resources management'),
('Office of Information Technology', 'IT', 'IT services and support'),
('Office of the Registrar', 'REG', 'Student records and registration'),
('Office of the Dean of Students', 'DEAN', 'Student discipline and welfare');
```

### **8. Testing Checklist**

#### **Functionality Tests**
- [ ] User login/logout from different offices
- [ ] Document creation and upload
- [ ] Document routing between offices
- [ ] Approval workflow
- [ ] File storage and retrieval
- [ ] AI features (summarize, analyze, generate)
- [ ] Search functionality
- [ ] Real-time notifications

#### **Performance Tests**
- [ ] Load testing with multiple concurrent users
- [ ] Database query performance
- [ ] File upload/download speeds
- [ ] Memory usage monitoring
- [ ] Response time measurements

#### **Security Tests**
- [ ] SQL injection prevention
- [ ] XSS protection
- [ ] File upload security
- [ ] Session management
- [ ] Access control verification

### **9. Go-Live Checklist**

#### **Final Steps**
- [ ] All tests passed
- [ ] Database optimized and indexed
- [ ] SSL certificate installed
- [ ] Backup procedures in place
- [ ] Monitoring systems active
- [ ] User training completed
- [ ] Documentation updated
- [ ] Support procedures established

#### **Post-Deployment**
- [ ] Monitor system performance for 48 hours
- [ ] Check error logs daily
- [ ] Verify backup procedures
- [ ] Gather user feedback
- [ ] Plan for regular maintenance

### **10. Support and Maintenance**

#### **Regular Maintenance Tasks**
- **Daily**: Check error logs, verify backups
- **Weekly**: Review security logs, update system
- **Monthly**: Database optimization, performance review
- **Quarterly**: Security audit, backup restoration test

#### **Emergency Procedures**
- **System Down**: Check logs, restart services, contact hosting provider
- **Data Loss**: Restore from backup, investigate cause
- **Security Breach**: Isolate system, investigate, patch vulnerabilities

---

## ⚠️ **IMPORTANT NOTES**

1. **Never use default passwords** in production
2. **Always use HTTPS** for production deployment
3. **Regular backups** are essential
4. **Monitor system performance** continuously
5. **Keep software updated** for security
6. **Test all features** before going live
7. **Have a rollback plan** ready

For technical support, contact your system administrator or development team.
