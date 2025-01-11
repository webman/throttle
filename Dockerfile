FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive TZ=Etc/UTC

RUN dpkg --add-architecture i386 && apt-get update && apt-get install -y \
    software-properties-common \
    build-essential \
    wget \
    curl \
    unzip \
    git \
    supervisor \
    cron \
    libpng-dev \
    libzip-dev \
    libssl-dev \
    libgmp-dev \
    rrdtool \
    cabextract \
    munin \
    wine \
    wine32 \
    wine32:i386 \
    libgd3:i386

RUN add-apt-repository ppa:ondrej/php \
    && apt-get update \
    && apt-get install -y \
    php5.6 \
    php5.6-dev \
    php5.6-fpm \
    php5.6-cli \
    php5.6-mbstring \
    php5.6-mysql \
    php5.6-zip \
    php5.6-xml \
    php5.6-gmp \
    php5.6-curl \
    && apt-get purge -y 'php8.*' \
    && apt-get autoremove -y \
    && apt-get clean \
    && update-alternatives --set php /usr/bin/php5.6 \
    && update-alternatives --set phpize /usr/bin/phpize5.6 \
    && update-alternatives --set php-config /usr/bin/php-config5.6 \
    && wget https://pear.php.net/go-pear.phar \
    && php5.6 go-pear.phar \
    && rm go-pear.phar

RUN set -e \
    && pecl channel-update pecl.php.net \
    && (pecl install apcu-4.0.11 || (echo "Error installing apcu" && exit 1)) \
    && (pecl install redis-4.3.0 || (echo "Error installing redis" && exit 1)) \
    && echo "extension=apcu.so" > /etc/php/5.6/mods-available/apcu.ini \
    && echo "extension=redis.so" > /etc/php/5.6/mods-available/redis.ini \
    && phpenmod apcu redis \
    && pecl clear-cache

RUN echo "* * * * * root /var/www/throttle/app/console.php crash:clean >> /var/log/cron.log 2>&1; /var/www/throttle/app/console.php crash:process -l 250 -u >> /var/log/cron.log 2>&1" > /etc/cron.d/cron-jobs \
    && echo "0 * * * * root /var/www/throttle/app/console.php user:update >> /var/log/cron.log 2>&1" >> /etc/cron.d/cron-jobs \
    && echo "15 */3 * * * root /var/www/throttle/app/console.php symbols:update >> /var/log/cron.log 2>&1" >> /etc/cron.d/cron-jobs \
    && echo "30 0 * * * root /var/www/throttle/app/console.php symbols:download >> /var/log/cron.log 2>&1" >> /etc/cron.d/cron-jobs \
    && echo "30 0 * * * root /var/www/throttle/app/console.php symbols:mozilla:download >> /var/log/cron.log 2>&1" >> /etc/cron.d/cron-jobs \
    && echo "0 0 * * * root truncate -s 0 /var/log/cron.log" >> /etc/cron.d/cron-jobs

RUN touch /var/log/cron.log && chmod 0644 /var/log/cron.log /etc/cron.d/cron-jobs
RUN crontab /etc/cron.d/cron-jobs

COPY --from=composer:1 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/throttle

RUN chown -R www-data:www-data . \
    && rm /etc/php/5.6/fpm/php-fpm.conf

EXPOSE 9000
CMD ["/usr/bin/supervisord"]
