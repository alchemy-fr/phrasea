#!/bin/bash

set +ex

rabbitmqctl add_vhost auth
rabbitmqctl add_vhost upload
rabbitmqctl add_vhost notify
rabbitmqctl set_permissions -p auth ${RABBITMQ_DEFAULT_USER} '.*' '.*' '.*'
rabbitmqctl set_permissions -p upload ${RABBITMQ_DEFAULT_USER} '.*' '.*' '.*'
rabbitmqctl set_permissions -p notify ${RABBITMQ_DEFAULT_USER} '.*' '.*' '.*'
