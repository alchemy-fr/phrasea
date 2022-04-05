#!/bin/bash

apps=(
  lib/acl-bundle
  lib/admin-bundle
  lib/admin-bundle
  lib/api-test
  lib/core-bundle
  lib/notify-bundle
  lib/oauth-server-bundle
  lib/remote-auth-bundle
  lib/report-bundle
  lib/report-sdk
  lib/storage-bundle
  auth/api
  expose/api
  notify/api
  uploader/api
  databox/api
)

for a in "${apps[@]}"; do
  echo "Updating $a..."
  (cd "$a" && composer update $1)
done
