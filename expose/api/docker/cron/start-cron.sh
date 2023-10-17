#!/bin/sh

set -e

/usr/bin/crontab /srv/app/docker/cron/app-crontab

printenv | grep -v "no_proxy" >> /etc/environment

for script in /srv/app/docker/cron/scripts/*.sh; do
    "$script"
done

/usr/sbin/crond -f -l 2
