FROM php:8.3-apache

# MySQL drivers used by the app (PDO in config/login/register, mysqli in db.php)
RUN docker-php-ext-install pdo_mysql mysqli \
    && a2enmod rewrite headers

# Railway terminates HTTPS at its proxy; trust X-Forwarded-Proto so PHP sees HTTPS
RUN echo 'SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on' > /etc/apache2/conf-available/railway-https.conf \
    && a2enconf railway-https \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

COPY . /var/www/html/
RUN mkdir -p /var/www/html/includes/cache \
    && chown -R www-data:www-data /var/www/html

# On start: keep exactly one Apache MPM (mod_php needs prefork; two loaded MPMs crash Apache),
# listen on Railway's $PORT, then run Apache in the foreground.
CMD ["sh", "-c", "rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* && a2enmod -q mpm_prefork && PORT=${PORT:-8080} && sed -i \"s/^Listen .*/Listen ${PORT}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:[0-9]*>/<VirtualHost *:${PORT}>/\" /etc/apache2/sites-available/000-default.conf && echo \"Starting Apache on port ${PORT}\" && exec apache2-foreground"]
