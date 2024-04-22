#!/bin/bash

. bin/vars.sh

bin/dev/app-all.sh pnpm install
bin/dev/sf-all.sh composer install

(cd databox/api && bin/console fos:elastica:populate)
