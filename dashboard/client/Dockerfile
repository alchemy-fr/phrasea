ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}nodejs-base:${BASE_TAG} AS client-build

COPY ./dashboard/client/docker/entrypoint.d/* /docker/entrypoint.d/
COPY ./lib/bash/configurator ./configurator
COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml turbo.json ./
COPY --chown=node:node ./lib/js ./lib/js
COPY --chown=node:node ./dashboard/client ./dashboard/client

USER node

WORKDIR /srv/workspace/dashboard/client

RUN pnpm install \
    && mv index.tpl.html index.html

RUN pnpm build

############

FROM ${REGISTRY_NAMESPACE}nginx-client-base:${BASE_TAG} AS client-nginx

COPY ./dashboard/client/docker/entrypoint.d/* /docker/entrypoint.d/
COPY ./lib/bash/configurator ./configurator
COPY ./dashboard/client/config-compiler.js .
COPY --from=client-build /srv/workspace/dashboard/client/dist .
COPY ./dashboard/client/git-log.html /var/app/

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}
