ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}nodejs-base:${BASE_TAG} AS client-build

COPY ./databox/client/docker/entrypoint.d/* /docker/entrypoint.d/
COPY ./lib/bash/configurator ./configurator
COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml turbo.json ./
COPY --chown=node:node ./lib/js ./lib/js
COPY --chown=node:node ./databox/client ./databox/client

USER node

WORKDIR /srv/workspace/databox/client

RUN pnpm install \
    && mv index.tpl.html index.html

RUN NODE_OPTIONS="--max-old-space-size=8192" && pnpm build

############

FROM ${REGISTRY_NAMESPACE}nginx-client-base:${BASE_TAG} AS client-nginx

COPY ./databox/client/docker/entrypoint.d/* /docker/entrypoint.d/
COPY ./lib/bash/configurator ./configurator
COPY ./databox/client/config-compiler.js .
COPY --from=client-build /srv/workspace/databox/client/dist .

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}
