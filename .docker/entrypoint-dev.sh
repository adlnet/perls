#!/bin/sh

if [ "${ENABLE_XDEBUG}" = true ]; then
  echo "Enabling xdebug..."
  cp /dev-tools/docker-php-ext-xdebug.ini /etc/php7/conf.d/docker-php-ext-xdebug.ini
else
  echo "Disabling xdebug..."
  rm /etc/php7/conf.d/docker-php-ext-xdebug.ini 2>&1 > /dev/null
fi

echo "Starting httpd..."
httpd -DFOREGROUND
