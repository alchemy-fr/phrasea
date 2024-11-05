ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}nodejs-base:${BASE_TAG} AS client-build

COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml turbo.json ./
COPY --chown=node:node ./lib/js ./lib/js
COPY --chown=node:node ./dashboard/client ./dashboard/client

USER node

WORKDIR /srv/workspace/dashboard/client

RUN pnpm install \
    && mv index.tpl.html index.html

RUN pnpm build

############

FROM nginx:1.17.6-alpine AS client-nginx

COPY --from=client-build /srv/workspace/dashboard/client/dist /var/app
COPY ./dashboard/client/docker/nginx/conf.d /etc/nginx/conf.d
COPY ./dashboard/client/docker/start-server.sh /

RUN apk add --no-cache libstdc++ \
    && apk add --virtual .build \
        wget \
    && mkdir -p /var/docker \
    && wget -q -O /var/docker/generate-env https://github.com/alchemy-fr/config-compiler/releases/download/v2.2.1/generate-env-alpine \
    && chmod +x /var/docker/generate-env \
    && apk del .build \
    && rm /etc/nginx/conf.d/default.conf

EXPOSE 80

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}

COPY --chown=node:node ./lib/bash/configurator /var/app//configurator

COPY ./dashboard/client/config-compiler.js /var/app/
COPY ./dashboard/client/git-log.html /var/app/

WORKDIR /var/app

CMD ["/start-server.sh"]
