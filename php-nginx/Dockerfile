FROM trafex/alpine-nginx-php7:1.10.0

USER root
# the scripts are still in apache folder
COPY docker/php-apache/scripts /installfiles/
COPY docker/php-nginx/nginx-conf.txt /installfiles/nginx-conf.txt
RUN chmod 755 /installfiles/build/domains-to-server-names.sh
ARG PROJECT_FOLDER_NAME
COPY ./ "/var/www/html/${PROJECT_FOLDER_NAME}"
RUN rm "/var/www/html/${PROJECT_FOLDER_NAME}/docker" -r

RUN set -x
#RUN docker-php-ext-install pdo_mysql
RUN apk --no-cache add php7-xsl php7-zip php7-iconv php7-tokenizer php7-simplexml php7-pdo_mysql
RUN apk --no-cache add bash
RUN apk --no-cache add npm
RUN npm install -g yarn # needed for webpack encore

COPY --from=composer /usr/bin/composer /usr/bin/composer

ARG WEBSITE_USER_PASSWORD
#RUN useradd website -p $WEBSITE_USER_PASSWORD
# Beware of : character in password
RUN addgroup -S website && adduser -S website -G website && echo "website:$WEBSITE_USER_PASSWORD" | chpasswd
ARG MAIN_DOMAIN
ARG ALL_DOMAINS
ARG PROJECT_FOLDER_NAME

RUN DOMAINS=`echo $ALL_DOMAINS | tr ',' ' '` ; sed -i "s,#PLACEHOLDER_DOMAINS, ${DOMAINS}," /installfiles/nginx-conf.txt
RUN sed -i "s,#PLACEHOLDER_PROJECTFOLDERNAME,${PROJECT_FOLDER_NAME}," /installfiles/nginx-conf.txt
RUN cat /installfiles/nginx-conf.txt > /etc/nginx/conf.d/symfony-nginx.conf
RUN sed -i '1s/^/user nobody nobody;\n/' /etc/nginx/nginx.conf
WORKDIR "/var/www/html/$PROJECT_FOLDER_NAME"

RUN echo -e "user = nobody\n" >> /etc/php7/php-fpm.d/www.conf
# The option L is to follow redirection
RUN curl -L "https://github.com/fabpot/local-php-security-checker/releases/download/v2.0.6/local-php-security-checker_2.0.6_linux_amd64" --output '/usr/bin/local-php-security-checker'
RUN chmod 755 /usr/bin/local-php-security-checker
RUN yarn install --dev # dev to have webpack encore
RUN composer install --no-dev --optimize-autoloader
#RUN composer dump-autoloader can be needed (something like that)
ARG GENERATE_JS_ROUTES
# generate routes here if needed
RUN bash /installfiles/build/dump-js-routes.sh
#RUN php bin/console fos:js-routing:dump --env=prod
#RUN php bin/console fos:js-routing:dump --env=prod --domain=admin
RUN yarn encore production
RUN php bin/console cache:clear --env=prod
RUN mkdir -p var
RUN chown -R nobody public/sitemap*

#RUN npm uninstall -g yarn # causes error "illegal operation on a directory..."
RUN npm uninstall -g yarn
RUN apk del npm
RUN rm /usr/bin/composer
RUN rm /usr/bin/local-php-security-checker

#
# /etc/php7/php.ini / /etc/php7/conf.d/custom.ini
RUN sed -i 's,^;extension=pdo_mysql$,extension=pdo_mysql,' /etc/php7/php.ini
RUN sed -i 's,^memory_limit =.*$,memory_limit = 4096M,' /etc/php7/php.ini
RUN sed -i 's,^listen =.*$,listen = /var/run/php/php7.4-fpm.sock,' /etc/php7/php-fpm.d/www.conf
#RUN docker-php-ext-enable pdo_mysql 
#RUN apk add cron


ARG EMAIL
ARG EMAIL_PASSWORD
ARG I_WANT_MAILSERVER
RUN bash /installfiles/build/replace-vars-in-env-file.sh
ARG MYSQL_USER
ARG MYSQL_PASSWORD
ARG MYSQL_DATABASE
RUN sed -i "s,^DATABASE_URL=.*$,DATABASE_URL=mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@database:3306/${MYSQL_DATABASE}," "/var/www/html/$PROJECT_FOLDER_NAME/.env"

RUN mkdir -p var/log
RUN chown -R nobody var
RUN mkdir -p /var/run/php
RUN chown -R nobody /var/run/php
COPY docker/php-nginx/www.conf /etc/php7/php-fpm.d/www.conf
#USER nobody

# the chown below is the only fix that worked
#CMD chown -R nobody var ; su nobody; /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
#CMD chown -R nobody var ; /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80