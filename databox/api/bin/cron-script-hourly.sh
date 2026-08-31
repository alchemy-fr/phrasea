#!/bin/sh

cd /srv/app/ \
  && bin/console alchemy:notifier:digest:flush
