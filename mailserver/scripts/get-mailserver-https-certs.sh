#!/bin/bash

envPath=${1:?Usage $0 docker/env/path/.env}

domains=`grep -Po "^\s*ALL_DOMAINS\s*=\s*\K.*(?=\s*)" $envPath | tr -d '\r\n'`
email=`grep -Po "^\s*YOUR_OWN_EMAIL\s*=\s*\K.*(?=\s*)" $envPath | tr -d '\r\n'`

if [ -z "$domains" ]
then
	echo "The ALL_DOMAINS variable isn't specified"
	exit 1
fi

if [ -z "$email" ]
then
	echo "The YOUR_OWN_EMAIL variable isn't specified"
	exit 1
fi

# to remove the quotes. it was supposed to be done with the regex, but somehow the '?' seems to break the lookahead
if [[ "$domains" == \"* ]]; then
	domains=`sed -e "s/^\"//" -e "s/\"$//" <<<"$domains"`
elif [[ "$domains" == \'* ]]; then
	domains=`sed -e "s/^'//" -e "s/'$//" <<<"$domains"`
fi

domains="-d ${domains//,/ -d }"

docker run --rm -it -v "${PWD}/certs/:/etc/letsencrypt/" -v "${PWD}/cert_logs/:/var/log/letsencrypt/" -p 80:80 certbot/certbot certonly --standalone $domains -m $email