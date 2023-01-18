#!/bin/bash

source /usr/local/bin/helpers/index.sh


function create_if_not_exist
{
  	local MAIL_ACCOUNT=$EMAIL
	__account_already_exists || setup email add $EMAIL $EMAIL_PASSWORD
	export EMAIL=null
	export EMAIL_PASSWORD=null
}

create_if_not_exist ; /usr/bin/dumb-init "${@}"