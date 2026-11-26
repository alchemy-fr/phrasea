ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}nodejs-base:${BASE_TAG} AS deps

COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml turbo.json ./
COPY --chown=node:node ./dashboard/client/package.json ./dashboard/client/package.json
# Workspace libraries (and the ones they depend on)
COPY --chown=node:node ./lib/js/core/package.json ./lib/js/core/package.json
COPY --chown=node:node ./lib/js/i18n/package.json ./lib/js/i18n/package.json
COPY --chown=node:node ./lib/js/theme/package.json ./lib/js/theme/package.json
COPY --chown=node:node ./lib/js/ui/package.json ./lib/js/ui/package.json

USER node
RUN pnpm install --filter "dashboard-client..." --frozen-lockfile \
    && mkdir -p /srv/workspace/phrasea/dashboard/client/.next

############

FROM deps AS client-dev

COPY --chown=node:node ./lib/js/core ./lib/js/core
COPY --chown=node:node ./lib/js/i18n ./lib/js/i18n
COPY --chown=node:node ./lib/js/theme ./lib/js/theme
COPY --chown=node:node ./lib/js/ui ./lib/js/ui
COPY --chown=node:node ./dashboard/client ./dashboard/client
WORKDIR /srv/workspace/phrasea/dashboard/client
ENV NODE_OPTIONS=
EXPOSE 3000
CMD ["pnpm", "dev"]

############

FROM deps AS client-build

COPY --chown=node:node ./lib/js/core ./lib/js/core
COPY --chown=node:node ./lib/js/i18n ./lib/js/i18n
COPY --chown=node:node ./lib/js/theme ./lib/js/theme
COPY --chown=node:node ./lib/js/ui ./lib/js/ui
COPY --chown=node:node ./dashboard/client ./dashboard/client
WORKDIR /srv/workspace/phrasea/dashboard/client
ENV NODE_OPTIONS=--max-old-space-size=4096 \
    NEXT_TELEMETRY_DISABLED=1
# build-info/ holds the commit history written by bin/git-log.sh, when run
RUN pnpm build && mkdir -p build-info

############

FROM node:26.12.1-alpine3.24 AS runner

ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    HOSTNAME=0.0.0.0 \
    PORT=3000

WORKDIR /app

RUN addgroup -S app && adduser -S -G app app

COPY --from=client-build --chown=app:app /srv/workspace/phrasea/dashboard/client/.next/standalone ./
COPY --from=client-build --chown=app:app /srv/workspace/phrasea/dashboard/client/.next/static ./dashboard/client/.next/static
COPY --from=client-build --chown=app:app /srv/workspace/phrasea/dashboard/client/public ./dashboard/client/public
COPY --from=client-build --chown=app:app /srv/workspace/phrasea/dashboard/client/build-info ./dashboard/client/build-info

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}

USER app
EXPOSE 3000

CMD ["node", "dashboard/client/server.js"]
