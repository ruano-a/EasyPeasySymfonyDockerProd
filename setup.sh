#!/bin/bash

chmod 755 ./update-yml.sh
./update-yml.sh

wantMailServer=`grep -Po "^\s*I_WANT_MAILSERVER\s*=\s*\K.*(?=\s*)" .env | tr -d '\r\n'`
wantMailServer=`echo "$wantMailServer" | tr '[:upper:]' '[:lower:]' `

if [ "$wantMailServer" = 'true' ]
then
	echo "Getting https for mailserver"
	chmod 755 ./mailserver/scripts/get-mailserver-https-certs.sh
	./mailserver/scripts/get-mailserver-https-certs.sh .env
else
	echo "No https needed"
fi