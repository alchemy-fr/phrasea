#!/bin/sh

if [ "${IS_WORKER}" == "1" ]; then
  WORKER_INI_FILE=/docker/worker/worker.ini
  if [ ! -f ${WORKER_INI_FILE} ]; then
    echo "Missing Worker template file ${WORKER_INI_FILE}"
    exit 1
  fi

  mkdir -p /etc/supervisor.d

  for i in ${WORKER_PRIORITIES}; do
    export WORKER_CHANNEL="${i}"
    envsubst < ${WORKER_INI_FILE} > /etc/supervisor.d/worker-${WORKER_CHANNEL}.ini
  done

  unset WORKER_CHANNEL
fi
