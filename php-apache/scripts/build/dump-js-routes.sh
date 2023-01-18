#!/bin/bash

GENERATE_JS_ROUTES=`echo "$GENERATE_JS_ROUTES" | tr '[:upper:]' '[:lower:]' `

if [ "$GENERATE_JS_ROUTES" = 'true' ];
    then php bin/console fos:js-routing:dump --env=prod
fi