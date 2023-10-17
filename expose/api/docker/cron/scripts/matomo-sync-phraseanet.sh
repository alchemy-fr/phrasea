#!/bin/sh

cd /srv/app \
  && bin/console app:matomo:sync-phraseanet
