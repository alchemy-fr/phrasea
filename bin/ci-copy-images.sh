#!/bin/bash
# Copies the images built by the CI, registry to registry (nothing is pulled
# nor unpacked on the runner): every compose service with a `build` section,
# in the active COMPOSE_PROFILES, from ${REGISTRY_NAMESPACE}<name>:${DOCKER_TAG}
# to <destination namespace><name>:<destination tag> — the images that
# `docker compose push` would push with those two variables changed.
#
# One image at a time, with retries: in parallel, ECR Public answers "Rate
# exceeded". Retrying is cheap, as the blobs already on the registry are skipped.
#
# Usage: bin/ci-copy-images.sh <destination namespace> <destination tag>
#   e.g. bin/ci-copy-images.sh public.ecr.aws/b2s9z7l1/ps- my-branch

set -eu -o pipefail

if [ $# -ne 2 ]; then
    echo "Usage: $0 <destination namespace> <destination tag>" >&2
    exit 2
fi

dest_namespace=$1
dest_tag=$2
attempts=5

mapfile -t sources < <(docker compose config --format json | jq -r '.services[] | select(.build) | .image' | sort -u)

if [ ${#sources[@]} -eq 0 ]; then
    echo "No image to copy" >&2
    exit 1
fi

destinations=()
for source in "${sources[@]}"; do
    case "${source}" in
        "${REGISTRY_NAMESPACE}"*":${DOCKER_TAG}") ;;
        *)
            echo "Unexpected image ${source}: not ${REGISTRY_NAMESPACE}<name>:${DOCKER_TAG}" >&2
            exit 1
            ;;
    esac
    name=${source#"${REGISTRY_NAMESPACE}"}
    name=${name%":${DOCKER_TAG}"}
    destinations+=("${dest_namespace}${name}:${dest_tag}")
done

echo "Images to copy:"
for i in "${!sources[@]}"; do
    echo "  ${sources[$i]} -> ${destinations[$i]}"
done

for i in "${!sources[@]}"; do
    source=${sources[$i]}
    destination=${destinations[$i]}
    for attempt in $(seq 1 $attempts); do
        # --prefer-index=false: a copy of the manifest as it is, not wrapped
        # in an index.
        if docker buildx imagetools create --prefer-index=false --tag "${destination}" "${source}"; then
            break
        fi
        if [ "$attempt" -eq "$attempts" ]; then
            echo "Copy of ${source} failed after $attempts attempts" >&2
            exit 1
        fi
        delay=$((attempt * 30))
        echo "Copy of ${source} failed (attempt $attempt/$attempts), retrying in ${delay}s"
        sleep "$delay"
    done
done
