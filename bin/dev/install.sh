#!/bin/bash

. bin/vars.sh

(cd novu/bridge && pnpm install)

pnpm install

bin/dev/sf-all.sh composer install

(cd databox/api && bin/console fos:elastica:populate)
