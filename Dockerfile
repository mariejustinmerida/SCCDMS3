# Force App Platform to build as PHP (avoids Python buildpack detection).
# Listens on 8080 for DigitalOcean App Platform.
FROM php:8.2-apache

# App Platform expects port 8080
RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
EXPOSE 8080

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# App document root
WORKDIR /var/www/html

# Copy app files (.dockerignore excludes vendor, .git, etc.)
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Apache: allow .htaccess
RUN a2enmod rewrite headers

# Ensure storage dirs exist and are writable
RUN mkdir -p storage/documents storage/logs storage/profiles storage/uploads \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage
