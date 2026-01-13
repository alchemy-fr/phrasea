#!/bin/sh

SYMFONY_PROJECTS="
databox/api
expose/api
uploader/api
"

CLIENT_PROJECTS="
databox/client
expose/client
uploader/client
dashboard/client
"

NODE_PROJECTS="
databox/indexer
"

PHP_LIBS="
lib/php/admin-bundle
lib/php/api-test
lib/php/auth-bundle
lib/php/configurator-bundle
lib/php/core-bundle
lib/php/es-bundle
lib/php/messenger-bundle
lib/php/metadata-manipulator-bundle
lib/php/notify-bundle
lib/php/rendition-factory
lib/php/rendition-factory-bundle
lib/php/report-bundle
lib/php/report-sdk
lib/php/storage-bundle
lib/php/test-bundle
lib/php/track-bundle
lib/php/webhook-bundle
lib/php/workflow
lib/php/workflow-bundle
"

JS_LIBS="
lib/js/api
lib/js/auth
lib/js/core
lib/js/i18n
lib/js/navigation
lib/js/notification
lib/js/phrasea-ui
lib/js/react-auth
lib/js/react-form
lib/js/react-hooks
lib/js/react-ps
lib/js/storage
lib/js/visual-workflow
"
