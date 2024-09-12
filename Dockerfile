# Use an official PHP image as a base
FROM php:8.3-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    zip \
    && docker-php-ext-install intl pdo pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite for Symfony
RUN a2enmod rewrite

# Change the default Apache document root to /public
RUN sed -i 's|/var/www/html|/var/www/html/public|' /etc/apache2/sites-available/000-default.conf

# Set the proper permissions for the Apache web server
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/

# Copy the application code into the container
COPY . /var/www/html

# Set the proper permissions for the uploads directory
RUN mkdir -p /var/www/html/public/uploads && \
    chown -R www-data:www-data /var/www/html/public/uploads && \
    chmod -R 755 /var/www/html/public/uploads

# Install Symfony dependencies using Composer
RUN composer install --prefer-dist --no-scripts --no-dev --no-interaction

# Expose port 80 for web traffic
EXPOSE 80

# Set the entry point to run Apache in the foreground
CMD ["apache2-foreground"]
