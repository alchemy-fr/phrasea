#!/bin/bash
#
# Creates or updates, as the MinIO root user (S3_ACCESS_KEY), the user of each
# consumer whose key pair is set, with a policy restricted to what it needs:
#
#   DATABOX_S3_*, EXPOSE_S3_*, UPLOADER_S3_*
#       the API, its workers and scheduler: every object operation in the
#       bucket of the application (multipart uploads and presigned URLs
#       included) and listing it, plus reading and writing config.json in the
#       configurator bucket (the admin of each API pushes the global
#       configuration there);
#   CONFIG_READER_S3_*
#       the Next.js clients: reading the configurator bucket;
#   INDEXER_S3_*
#       databox-indexer: listing and reading INDEXER_BUCKET_NAME.
#
# The bucket configuration (policy, notifications, versioning, deletion) stays
# with the root user. A consumer without a pair keeps the root key, and nothing
# is done when no pair is set.
#
# Idempotent: the policy is replaced, the secret of the user reset and the user
# left with its own policy only. A pair emptied afterwards does not remove the
# user (mc admin user remove).
#
# Needs bash and mc only: the MinIO server image ships mc, but neither grep nor
# sed.

set -euo pipefail

S3_URL="${S3_INTERNAL_URL:-http://minio:9000}"
WAIT_TIMEOUT="${MINIO_WAIT_TIMEOUT:-120}"
ALIAS=phrasea
POLICY_PREFIX=phrasea-

fail() {
    echo "$*" >&2
    exit 1
}

# Usage: check_bucket <variable name>
check_bucket() {
    local value="${!1:-}"
    if [ -z "${value}" ]; then
        fail "${1} is empty."
    fi
    if ! [[ "${value}" =~ ^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$ ]]; then
        fail "${1} is not a valid bucket name: ${value}"
    fi
}

# Usage: check_pair <variable prefix>; returns 1 when the pair is empty.
check_pair() {
    local key_var="${1}_ACCESS_KEY" secret_var="${1}_SECRET_KEY"
    local key="${!key_var:-}" secret="${!secret_var:-}"

    if [ -z "${key}" ] && [ -z "${secret}" ]; then
        return 1
    fi
    if [ -z "${key}" ] || [ -z "${secret}" ]; then
        fail "Set both ${key_var} and ${secret_var}, or neither."
    fi
    if [ "${key}" = "${S3_ACCESS_KEY}" ]; then
        fail "${key_var} is the MinIO root user (S3_ACCESS_KEY): choose another access key."
    fi
    if [ ${#key} -lt 3 ]; then
        fail "${key_var} must be at least 3 characters long."
    fi
    if [ ${#secret} -lt 8 ]; then
        fail "${secret_var} must be at least 8 characters long."
    fi
}

statement() {
    local actions="$1" resource="$2"
    printf '{"Effect":"Allow","Action":[%s],"Resource":["arn:aws:s3:::%s"]}' "${actions}" "${resource}"
}

policy() {
    local IFS=,
    printf '{"Version":"2012-10-17","Statement":[%s]}' "$*"
}

BUCKET_LIST_ACTIONS='"s3:GetBucketLocation","s3:ListBucket"'
OBJECT_ACTIONS='"s3:GetObject","s3:PutObject","s3:DeleteObject","s3:AbortMultipartUpload","s3:ListMultipartUploadParts"'

# Usage: application_policy <bucket>
application_policy() {
    policy \
        "$(statement "${BUCKET_LIST_ACTIONS},\"s3:ListBucketMultipartUploads\"" "$1")" \
        "$(statement "${OBJECT_ACTIONS}" "$1/*")" \
        "$(statement '"s3:GetObject","s3:PutObject"' "${CONFIGURATOR_S3_BUCKET_NAME}/${S3_PATH_PREFIX:-}config.json")"
}

# Usage: config_reader_policy <configurator bucket>
config_reader_policy() {
    policy "$(statement '"s3:GetObject"' "$1/${S3_PATH_PREFIX:-}*")"
}

# Usage: indexer_policy <bucket>
indexer_policy() {
    policy \
        "$(statement "${BUCKET_LIST_ACTIONS}" "$1")" \
        "$(statement '"s3:GetObject"' "$1/*")"
}

# The consumers whose pair is set: "<variable prefix> <policy suffix> <policy function> <bucket variable>"
consumers=()
for app in DATABOX EXPOSE UPLOADER; do
    if check_pair "${app}_S3"; then
        consumers+=("${app}_S3 ${app,,} application_policy ${app}_S3_BUCKET_NAME")
    fi
done
if check_pair CONFIG_READER_S3; then
    consumers+=("CONFIG_READER_S3 config-reader config_reader_policy CONFIGURATOR_S3_BUCKET_NAME")
fi
if check_pair INDEXER_S3; then
    consumers+=("INDEXER_S3 indexer indexer_policy INDEXER_BUCKET_NAME")
fi

if [ ${#consumers[@]} -eq 0 ]; then
    echo "No *_S3_ACCESS_KEY pair set: every consumer uses the MinIO root user."
    exit 0
fi

if [ -z "${S3_ACCESS_KEY:-}" ] || [ -z "${S3_SECRET_KEY:-}" ]; then
    fail "S3_ACCESS_KEY and S3_SECRET_KEY (the MinIO root user) are required."
fi
check_bucket CONFIGURATOR_S3_BUCKET_NAME
if [[ "${S3_PATH_PREFIX:-}" == *[\"\\*?]* ]]; then
    fail "S3_PATH_PREFIX cannot contain any of: \" \\ * ?"
fi

declare -A owners=()
for consumer in "${consumers[@]}"; do
    read -r prefix _ _ bucket_var <<< "${consumer}"
    key_var="${prefix}_ACCESS_KEY"
    key="${!key_var}"
    if [ -n "${owners["${key}"]:-}" ]; then
        fail "${key_var} and ${owners["${key}"]} are the same access key: give each consumer its own."
    fi
    owners["${key}"]="${key_var}"
    check_bucket "${bucket_var}"
done

echo "Waiting for MinIO at ${S3_URL}..."
deadline=$((SECONDS + WAIT_TIMEOUT))
until mc alias set "${ALIAS}" "${S3_URL}" "${S3_ACCESS_KEY}" "${S3_SECRET_KEY}" >/dev/null 2>&1 \
    && mc admin info "${ALIAS}" >/dev/null 2>&1; do
    if [ ${SECONDS} -ge ${deadline} ]; then
        echo "MinIO not ready after ${WAIT_TIMEOUT}s, or S3_ACCESS_KEY/S3_SECRET_KEY is not its root user:" >&2
        mc admin info "${ALIAS}" >&2 || true
        exit 1
    fi
    sleep 2
done

POLICY_FILE=$(mktemp)
trap 'rm -f "${POLICY_FILE}"' EXIT

# Usage: attached_policies <access key>; one policy name per line.
attached_policies() {
    local info names name
    info=$(mc admin user info "${ALIAS}" "$1" --json)
    if [[ "${info}" =~ \"policyName\":\"([^\"]*)\" ]]; then
        IFS=, read -r -a names <<< "${BASH_REMATCH[1]}"
        for name in "${names[@]}"; do
            printf '%s\n' "${name}"
        done
    fi
}

for consumer in "${consumers[@]}"; do
    read -r prefix suffix policy_function bucket_var <<< "${consumer}"
    key_var="${prefix}_ACCESS_KEY"
    secret_var="${prefix}_SECRET_KEY"
    key="${!key_var}"
    policy_name="${POLICY_PREFIX}${suffix}"

    "${policy_function}" "${!bucket_var}" > "${POLICY_FILE}"
    mc admin policy create "${ALIAS}" "${policy_name}" "${POLICY_FILE}" >/dev/null
    mc admin user add "${ALIAS}" "${key}" "${!secret_var}" >/dev/null

    attached=0
    while IFS= read -r name; do
        if [ "${name}" = "${policy_name}" ]; then
            attached=1
        elif [ -n "${name}" ]; then
            mc admin policy detach "${ALIAS}" "${name}" --user "${key}" >/dev/null
            echo "Policy ${name} detached from ${key}"
        fi
    done < <(attached_policies "${key}")
    if [ ${attached} -eq 0 ]; then
        mc admin policy attach "${ALIAS}" "${policy_name}" --user "${key}" >/dev/null
    fi

    echo "MinIO user ${key} (${key_var}) has the policy ${policy_name}"
done
