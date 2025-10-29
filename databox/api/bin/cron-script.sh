#!/bin/sh

cd /srv/app/ \
  && bin/console app:trash:empty
