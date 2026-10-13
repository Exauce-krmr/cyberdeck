FROM php:8.5-apache

# Extension MySQL/MariaDB pour PDO, réécriture d'URL (.htaccess), racine web = public/
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
 && printf '<Directory /var/www/html/public>\n  AllowOverride All\n  Require all granted\n</Directory>\n' \
      > /etc/apache2/conf-available/toptrump.conf \
 && a2enconf toptrump

RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip git \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Les dépendances sont installées dans l'image : seul le code est monté en volume (voir docker-compose.yml)
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist

COPY . .
# Cache des vues Blade (reste dans le conteneur)
RUN mkdir -p storage/cache && chown -R www-data:www-data storage
