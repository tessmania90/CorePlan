# Wir nutzen ein schlankes, offizielles PHP 8.2 Image mit Apache
FROM php:8.2-apache

# Aktiviere Apache Rewrite und SSL
RUN a2enmod rewrite ssl

# ERLAUBE .htaccess OVERRIDES
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Installiere SQLite, PDO und OpenSSL
RUN apt-get update && apt-get install -y libsqlite3-dev openssl \
    && docker-php-ext-install pdo pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Generiere ein Self-Signed Zertifikat (Gebrandet auf Tessmann Digital)
# Die Pfade entsprechen genau denen, die Apache in der default-ssl.conf erwartet
RUN openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/ssl-cert-snakeoil.key \
    -out /etc/ssl/certs/ssl-cert-snakeoil.pem \
    -subj "/C=DE/ST=Brandenburg/L=Gruenheide/O=Tessmann Digital/CN=localhost"

# Aktiviere den virtuellen SSL-Host von Apache
RUN a2ensite default-ssl

# Setze das Arbeitsverzeichnis
COPY src/ /var/www/html/
WORKDIR /var/www/html