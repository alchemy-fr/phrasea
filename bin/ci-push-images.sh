#!/bin/bash
# Pushes the images of the compose services, one at a time: a parallel push
# exceeds the request rate limit of ECR Public ("Rate exceeded"). Retrying is
# cheap, as the layers already on the registry are skipped.

set -u

attempts=5

for attempt in $(seq 1 $attempts); do
    docker compose --parallel 1 push && exit 0

    if [ "$attempt" -lt "$attempts" ]; then
        delay=$((attempt * 30))
        echo "Push failed (attempt $attempt/$attempts), retrying in ${delay}s"
        sleep "$delay"
    fi
done

echo "Push failed after $attempts attempts"
exit 1
