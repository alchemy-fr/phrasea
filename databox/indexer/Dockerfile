ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}nodejs-base:${BASE_TAG} AS client-build

ENV SERVER_PORT=3000

COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml turbo.json ./
COPY --chown=node:node ./lib/js ./lib/js

WORKDIR /srv/workspace/phrasea/databox/indexer

COPY --chown=node:node ./databox/indexer /srv/workspace/phrasea/databox/indexer

USER node

WORKDIR /srv/workspace/phrasea/databox/indexer

RUN pnpm install \
    && pnpm build \
    && chmod +x /entrypoint.sh

EXPOSE ${SERVER_PORT}

ENTRYPOINT ["/srv/workspace/phrasea/databox/indexer/docker/entrypoint.sh"]
