FROM php:8.2-apache

# SQLite3 desteğini etkinleştir
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Apache mod_rewrite'ı etkinleştir
RUN a2enmod rewrite

# Çalışma dizinini ayarla
WORKDIR /var/www/html

# Dosyaları kopyala
COPY src/ /var/www/html/

# İzinleri ayarla
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Database klasörü için yazma izni
RUN mkdir -p /var/www/html/database \
    && chmod -R 777 /var/www/html/database

EXPOSE 80