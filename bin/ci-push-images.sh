#!/bin/bash
# Pushes the images of the compose services, one at a time: a parallel push
# exceeds the request rate limit of ECR Public ("Rate exceeded"). Retrying is
# cheap, as the layers already on the registry are skipped.
#
# Only a transient error (rate limit, server error, timeout, connection reset)
# is retried: a missing repository or an expired login fails at once.

set -u
set -o pipefail

attempts=5
transient='Rate exceeded|429|toomanyrequests|50[0-9]|timeout|connection reset'

for attempt in $(seq 1 $attempts); do
    output=$(docker compose --parallel 1 push 2>&1 | tee /dev/stderr) && exit 0

    if ! grep -qiE "$transient" <<< "$output"; then
        echo "Push failed with a non transient error"
        exit 1
    fi

    if [ "$attempt" -lt "$attempts" ]; then
        delay=$((attempt * 30))
        echo "Push failed (attempt $attempt/$attempts), retrying in ${delay}s"
        sleep "$delay"
    fi
done

echo "Push failed after $attempts attempts"
exit 1
