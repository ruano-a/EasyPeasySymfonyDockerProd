#!/bin/bash

EMAIL=`php -r "echo urlencode('$EMAIL');"`
EMAIL_PASSWORD=`php -r "echo urlencode('$EMAIL_PASSWORD');"`
I_WANT_MAILSERVER=`echo "$I_WANT_MAILSERVER" | tr '[:upper:]' '[:lower:]' `

if [ "$I_WANT_MAILSERVER" = 'true' ];
    then sed -i "s,^MAILER_URL=.*$,MAILER_URL=smtp://mailserver:25?username=${EMAIL}\&password=${EMAIL_PASSWORD}," "/var/www/html/$PROJECT_FOLDER_NAME/.env"
fi

if [ "$I_WANT_MAILSERVER" = 'true' ];
    then sed -i "s,^MAILER_DSN=.*$,MAILER_DSN=smtp://mailserver:25?username=${EMAIL}\&password=${EMAIL_PASSWORD}," "/var/www/html/$PROJECT_FOLDER_NAME/.env" ;
fi