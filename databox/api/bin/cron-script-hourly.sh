#!/bin/sh

cd /srv/app/ \
  && bin/console alchemy:notifier:digest:flush \
  && bin/console app:integration:renew-tokens
