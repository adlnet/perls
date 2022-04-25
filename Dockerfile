ARG ALPINE_VERSION=3.14

# for template build
FROM alpine:${ALPINE_VERSION} as template-build
ARG ALPINE_VERSION

RUN apk add --no-cache nodejs npm python3

WORKDIR /theme
COPY web/themes/custom/perls /theme

RUN npm rebuild node-sass && npm install
RUN ./node_modules/.bin/gulp build-theme

# for production usage
FROM alpine:${ALPINE_VERSION} as prod
ARG ALPINE_VERSION
ARG version

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS 0
ENV APP_PATH /var/www/html

RUN apk add --no-cache \
    apache2 php7 php7-apache2 \
    php7-gd php7-zip php7-dom php7-tokenizer php7-pdo_mysql php7-pdo_sqlite php7-session php7-xml \
    php7-exif php7-xmlwriter php7-xmlreader php7-ctype php7-simplexml php7-opcache php7-soap php7-sodium \
    bash patch git composer wget php7-pecl-redis shadow

RUN usermod -u 1000 apache && groupmod -g 1000 apache

COPY .docker/php.ini /etc/php7/conf.d/99_php.ini
COPY .docker/php.opcache.ini /etc/php7/conf.d/99_opcache.ini
COPY .docker/apache-default.conf /etc/apache2/conf.d/default.conf
RUN chown -R apache:apache /tmp

WORKDIR $APP_PATH

COPY --chown=root:apache . $APP_PATH

ENV VERSION=${version}

RUN composer install --no-dev --no-cache

ENV PATH $PATH:$APP_PATH/vendor/drush/drush
COPY --from=template-build --chown=root:apache /theme $APP_PATH/web/themes/custom/perls

EXPOSE 80

CMD httpd -DFOREGROUND

# for development usage
FROM prod as dev

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS 1

RUN apk add --no-cache tini openssh-client mariadb-client nodejs npm rsync php7-pecl-xdebug $PHPIZE_DEPS

RUN composer install --no-cache

RUN mkdir /dev-tools
COPY .docker/docker-php-ext-xdebug.ini /dev-tools/docker-php-ext-xdebug.ini
COPY .docker/entrypoint-dev.sh /entrypoint-dev.sh

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["/entrypoint-dev.sh"]
