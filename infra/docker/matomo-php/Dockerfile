FROM matomo:4.14.2-fpm-alpine

ADD config.ini.php /usr/src/matomo/config/.config.ini.php

RUN touch /var/log/cron \
    && echo "5 * * * * php /var/www/html/console core:archive > /dev/null" >> /var/spool/cron/crontabs/root

ADD entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh
