FROM php:8.3-apache

ARG APHOST_VERSION=dev
ENV APHOST_VERSION=${APHOST_VERSION}

# Install system deps and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        sudo \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Set up app
WORKDIR /opt/aphost
COPY . .

# Apache vhost for the app
COPY docker/apache-aphost.conf /etc/apache2/sites-available/aphost.conf
RUN a2dissite 000-default.conf && a2ensite aphost.conf

# Vhost template
RUN mkdir -p /etc/aphost
COPY config/vhost.conf.tpl /etc/aphost/vhost.conf.tpl

# Privileged helper
COPY bin/vhost-admin-helper.sh /usr/local/sbin/vhost-admin-helper
RUN chmod 755 /usr/local/sbin/vhost-admin-helper

# Allow www-data to run the helper as root without a password
RUN echo "www-data ALL=(root) NOPASSWD: /usr/local/sbin/vhost-admin-helper" \
        > /etc/sudoers.d/aphost-helper \
    && chmod 0440 /etc/sudoers.d/aphost-helper

# Storage directories
RUN mkdir -p storage/data storage/logs \
    && chown -R www-data:www-data storage

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
