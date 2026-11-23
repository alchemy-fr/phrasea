#!/bin/sh

# WORKER_PRIORITIES: space-separated entries, one supervisor program each.
# An entry lists its receivers separated by "," in priority order (messenger:consume
# goes back to the first receiver after each handled message), with an optional
# ":N" suffix setting its number of processes (default 1): "p1 p1,p2 p1,p2,heavy:2".

if [ "${IS_WORKER}" == "1" ]; then
  WORKER_INI_FILE=/docker/worker/worker.ini
  if [ ! -f ${WORKER_INI_FILE} ]; then
    echo "Missing Worker template file ${WORKER_INI_FILE}"
    exit 1
  fi

  mkdir -p /etc/supervisor.d

  for i in ${WORKER_PRIORITIES}; do
    receivers="${i%%:*}"
    numprocs=1
    if [ "${receivers}" != "${i}" ]; then
      numprocs="${i#*:}"
    fi

    case "${numprocs}" in
      ''|*[!0-9]*)
        echo "Invalid WORKER_PRIORITIES entry \"${i}\": the number of processes must be an integer >= 1"
        exit 1
        ;;
    esac
    if [ "${numprocs}" -lt 1 ]; then
      echo "Invalid WORKER_PRIORITIES entry \"${i}\": the number of processes must be an integer >= 1"
      exit 1
    fi
    if [ -z "${receivers}" ]; then
      echo "Invalid WORKER_PRIORITIES entry \"${i}\": no receiver"
      exit 1
    fi

    export WORKER_PROGRAM="$(echo "${receivers}" | tr ',' '-')"
    export WORKER_RECEIVERS="$(echo "${receivers}" | tr ',' ' ')"
    export WORKER_NUMPROCS="${numprocs}"
    envsubst < ${WORKER_INI_FILE} > /etc/supervisor.d/worker-${WORKER_PROGRAM}.ini
  done

  unset WORKER_PROGRAM WORKER_RECEIVERS WORKER_NUMPROCS receivers numprocs
fi
