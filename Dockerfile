FROM php:8.2-apache

# Install database driver dependencies and compile PDO drivers
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libsqlite3-dev \
    ca-certificates \
    openssl \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql \
    && update-ca-certificates \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy project files into Apache document root
COPY . /var/www/html/

# Ensure Apache www-data user has proper permissions to read project files (especially db/ca-cert.pem)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80
EXPOSE 80
