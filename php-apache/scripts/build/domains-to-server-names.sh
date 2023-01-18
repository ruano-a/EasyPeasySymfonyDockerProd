#!/bin/bash
IFS=',' read -ra DOMAINS <<< $1
for i in "${!DOMAINS[@]}"; do
  if [ "$i" = 0 ]; then
    echo "    ServerName ${DOMAINS[$i]}"
  else
    echo "    ServerAlias ${DOMAINS[$i]}"
  fi
done
