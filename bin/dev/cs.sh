#!/bin/bash

. bin/vars.sh

bin/dev/js-all.sh pnpm format
bin/dev/js-all.sh pnpm lint:fix
bin/dev/sf-all.sh composer cs
