#!/bin/sh

cd /srv/app/ \
  && bin/console app:trash:empty \
  && bin/console alchemy:notifier:digest:flush
