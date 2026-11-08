FROM php:8.3-apache
RUN a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true \
 && a2enmod mpm_prefork rewrite headers \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
 && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
 && { echo "upload_max_filesize=6M"; echo "post_max_size=8M"; echo "expose_php=Off"; } > /usr/local/etc/php/conf.d/app.ini
COPY . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh && chown -R www-data:www-data /var/www/html
ENV DATA_DIR=/data
EXPOSE 8080
CMD ["/usr/local/bin/docker-entrypoint.sh"]
