FROM php:7.4-apache
COPY docker/php-apache/scripts /installfiles/
COPY docker/php-apache/apache-conf.txt /installfiles/apache-conf.txt
RUN chmod 755 /installfiles/build/domains-to-server-names.sh
ARG PROJECT_FOLDER_NAME
COPY ./ "/var/www/html/${PROJECT_FOLDER_NAME}"
RUN rm "/var/www/html/${PROJECT_FOLDER_NAME}/docker" -r

RUN a2enmod rewrite
RUN set -x
RUN apt update
RUN apt upgrade -y
#RUN apt install -y git
RUN docker-php-ext-install pdo_mysql
# icu-dev is needed for intl
RUN apt install -y libicu-dev \
&& docker-php-ext-configure intl \
&& docker-php-ext-install intl
RUN apt install -y libxml2-dev 
RUN docker-php-ext-install xml
RUN apt install -y libxslt1-dev \
&& docker-php-ext-install xsl
RUN apt install -y libzip-dev 
RUN docker-php-ext-install zip
RUN docker-php-ext-install mysqli
RUN apt remove cmdtest
RUN apt remove yarn
RUN apt install -y bash
RUN apt install npm -y
RUN npm install -g yarn # needed for webpack encore
RUN apt autoremove

COPY --from=composer /usr/bin/composer /usr/bin/composer

ARG WEBSITE_USER_PASSWORD
RUN useradd website -p $WEBSITE_USER_PASSWORD

ARG MAIN_DOMAIN
ARG ALL_DOMAINS
ARG PROJECT_FOLDER_NAME

# the replacement of #PLACEHOLDER_SERVERNAMES must be done before #PLACEHOLDER_SERVERNAME
RUN SERVERNAMES=`/installfiles/build/domains-to-server-names.sh ${ALL_DOMAINS}` ; awk -v r="$SERVERNAMES" '{gsub(/^#PLACEHOLDER_SERVERNAMES.*$/,r)}1' /installfiles/apache-conf.txt > /installfiles/apache-conf-tmp.txt
RUN mv /installfiles/apache-conf-tmp.txt /installfiles/apache-conf.txt

RUN sed -i "s,#PLACEHOLDER_SERVERNAME, ${MAIN_DOMAIN}," /installfiles/apache-conf.txt
RUN sed -i "s,#PLACEHOLDER_PROJECTFOLDERNAME,${PROJECT_FOLDER_NAME}," /installfiles/apache-conf.txt
RUN cat /installfiles/apache-conf.txt > /etc/apache2/sites-available/000-default.conf
WORKDIR "/var/www/html/$PROJECT_FOLDER_NAME"

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
RUN chown -R www-data public/sitemap*

#RUN npm uninstall -g yarn # causes error "illegal operation on a directory..."
RUN rm -r /usr/local/lib/node_modules/*
RUN apt remove -y npm
RUN rm /usr/bin/composer
RUN rm /usr/bin/local-php-security-checker

# mail server ?
RUN sed -i 's,^memory_limit =.*$,memory_limit = 4096M,' /usr/local/etc/php/php.ini-production
RUN sed -i 's,^memory_limit =.*$,memory_limit = 4096M,' /usr/local/etc/php/php.ini-development
RUN docker-php-ext-enable pdo_mysql 
RUN apt install cron -y


ARG EMAIL
ARG EMAIL_PASSWORD
ARG I_WANT_MAILSERVER
RUN bash /installfiles/build/replace-vars-in-env-file.sh
ARG MYSQL_USER
ARG MYSQL_PASSWORD
ARG MYSQL_DATABASE
RUN sed -i "s,^DATABASE_URL=.*$,DATABASE_URL=mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@database:3306/${MYSQL_DATABASE}," "/var/www/html/$PROJECT_FOLDER_NAME/.env"

RUN mkdir -p var/log
RUN chown -R www-data var

# the chown below is the only fix that worked
CMD chown -R www-data var ; apachectl -D FOREGROUND

EXPOSE 80