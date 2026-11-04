FROM matomo:4.14.2-fpm-alpine

ADD config.ini.php /usr/src/matomo/config/.config.ini.php

# The session upload progress handler is exploitable before any authentication
# (CVE-2026-109618, CVE-2026-109619) and Matomo does not rely on it.
RUN printf "session.upload_progress.enabled = Off\n" > $PHP_INI_DIR/conf.d/zz-session-upload-progress.ini

RUN touch /var/log/cron \
    && echo "5 * * * * php /var/www/html/console core:archive > /dev/null" >> /var/spool/cron/crontabs/root

ADD entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh
