FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    unzip \
    && docker-php-ext-install pdo pdo_sqlite

RUN a2enmod rewrite

COPY . /var/www/html

ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data /var/www/html

# Ustaw uprawnienia do bazy danych
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html/data && \
    chmod -R 755 /var/www/html/data

WORKDIR /var/www/html

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Upewnij się, że baza danych ma odpowiednie uprawnienia
RUN if [ -f /var/www/html/data/mydb.sqlite ]; then \
        chown www-data:www-data /var/www/html/data/mydb.sqlite && \
        chmod 664 /var/www/html/data/mydb.sqlite; \
    fi