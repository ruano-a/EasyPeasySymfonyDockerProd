#!/bin/bash

docker run -it -v ${PWD}/:/myinstall/ php:7.4-apache php /myinstall/php-apache/scripts/update-docker-compose.php -c /myinstall/docker-compose.yml -e /myinstall/.env -a /myinstall/acme.json
