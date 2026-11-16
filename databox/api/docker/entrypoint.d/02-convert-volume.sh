#!/bin/sh

# Volume shared with databox-converter (worker image only): TMPDIR of the worker, private to it,
# and the job directories, which the converter hands to the uid of each job.
if [ -d /var/convert ] && [ "$(id -u)" = "0" ]; then
  mkdir -p /var/convert/worker /var/convert/jobs
  chown app:app /var/convert/worker /var/convert/jobs
  chmod 0700 /var/convert/worker
  chmod 0711 /var/convert/jobs
fi
