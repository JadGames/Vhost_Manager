FROM php:8.3-apache

ARG VHM_VERSION=dev
ENV VHM_VERSION=${VHM_VERSION}

# Install system deps and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        sudo \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Set up app
WORKDIR /opt/vhost-manager
COPY . .

# Apache vhost for the app
COPY docker/apache-vhost-manager.conf /etc/apache2/sites-available/vhost-manager.conf
RUN a2dissite 000-default.conf && a2ensite vhost-manager.conf

# Vhost template
RUN mkdir -p /etc/vhost-manager
COPY config/vhost.conf.tpl /etc/vhost-manager/vhost.conf.tpl

# Privileged helper
COPY bin/vhost-admin-helper.sh /usr/local/sbin/vhost-admin-helper
RUN chmod 755 /usr/local/sbin/vhost-admin-helper

# Allow www-data to run the helper as root without a password
RUN echo "www-data ALL=(root) NOPASSWD: /usr/local/sbin/vhost-admin-helper" \
        > /etc/sudoers.d/vhost-manager-helper \
    && chmod 0440 /etc/sudoers.d/vhost-manager-helper

# Storage directories
RUN mkdir -p storage/data storage/logs \
    && chown -R www-data:www-data storage

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
