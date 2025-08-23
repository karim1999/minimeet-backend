FROM php:8.3-fpm

LABEL maintainer="Minimeet Container"

ARG NODE_VERSION=22
ARG MYSQL_CLIENT="default-mysql-client"
ARG WWWGROUP=1000
ARG USERID=1000

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

WORKDIR /var/www/html

# Set timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Install dependencies and tools
RUN apt-get update && apt-get upgrade -y && \
    apt-get install -y --no-install-recommends \
    apt-transport-https ca-certificates gnupg2 lsb-release \
    curl zip unzip git supervisor sqlite3 dnsutils \
    libpng-dev libjpeg-dev libfreetype6-dev libonig-dev \
    libxml2-dev libzip-dev libssl-dev libpq-dev libicu-dev \
    libldap2-dev libgmp-dev libmemcached-dev libmagickwand-dev \
    libreadline-dev librsvg2-bin libxslt1-dev ffmpeg fswatch \
    chromium nginx-light nano libcap2-bin && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
    pdo pdo_mysql pdo_pgsql mysqli pgsql zip intl bcmath \
    soap sockets pcntl opcache exif gd gettext ldap gmp xml && \
    pecl install redis igbinary msgpack xdebug memcached swoole imagick pcov && \
    docker-php-ext-enable redis igbinary msgpack xdebug memcached swoole imagick pcov && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Node.js, Yarn, Bun, pnpm setup
RUN mkdir -p /etc/apt/keyrings && \
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg && \
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list && \
    curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor -o /etc/apt/keyrings/yarn.gpg && \
    echo "deb [signed-by=/etc/apt/keyrings/yarn.gpg] https://dl.yarnpkg.com/debian/ stable main" > /etc/apt/sources.list.d/yarn.list && \
    apt-get update && \
    apt-get install -y nodejs yarn $MYSQL_CLIENT && \
    npm install -g npm pnpm bun && \
    apt-get -y autoremove && apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Allow php-fpm to bind to lower ports
RUN setcap "cap_net_bind_service=+ep" $(which php-fpm)

# PHP-FPM config for user
RUN mkdir -p /.composer && chmod -R a+rw /.composer && \
    groupadd --force -g $WWWGROUP sail && \
    useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u $USERID sail

# Copy PHP config
COPY php.ini /usr/local/etc/php/conf.d/99-custom.ini

RUN chown -R sail:sail /var/www/html
RUN chmod -R ug+rw /var/www/html

USER sail

EXPOSE 9000

CMD ["php-fpm"]
