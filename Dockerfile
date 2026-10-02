FROM php:8.3-apache

# MySQL drivers used by the app (PDO in config/login/register, mysqli in db.php)
RUN docker-php-ext-install pdo_mysql mysqli \
    && a2enmod rewrite headers

# Railway terminates HTTPS at its proxy; trust X-Forwarded-Proto so PHP sees HTTPS
RUN echo 'SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on' > /etc/apache2/conf-available/railway-https.conf \
    && a2enconf railway-https

# Pass Railway environment variables (DB, Google OAuth) through to PHP's getenv()
RUN echo 'variables_order = "EGPCS"' > /usr/local/etc/php/conf.d/env.ini

COPY . /var/www/html/
RUN mkdir -p /var/www/html/includes/cache \
    && chown -R www-data:www-data /var/www/html

# Railway injects $PORT at runtime; make Apache listen on it
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-8080}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-8080}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
