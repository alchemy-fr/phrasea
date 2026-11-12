ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}nodejs-base:${BASE_TAG} AS deps

COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml turbo.json ./
COPY --chown=node:node ./uploader/client/package.json ./uploader/client/package.json
# Workspace libraries (and the ones they depend on)
COPY --chown=node:node ./lib/js/next-core/package.json ./lib/js/next-core/package.json
COPY --chown=node:node ./lib/js/next-i18n/package.json ./lib/js/next-i18n/package.json
COPY --chown=node:node ./lib/js/next-theme/package.json ./lib/js/next-theme/package.json
COPY --chown=node:node ./lib/js/next-ui/package.json ./lib/js/next-ui/package.json
COPY --chown=node:node ./lib/js/api/package.json ./lib/js/api/package.json
COPY --chown=node:node ./lib/js/core/package.json ./lib/js/core/package.json

USER node
RUN pnpm install --filter "uploader-client..." --frozen-lockfile \
    && mkdir -p /srv/workspace/phrasea/uploader/client/.next

############

FROM deps AS client-dev

COPY --chown=node:node ./lib/js/api ./lib/js/api
COPY --chown=node:node ./lib/js/core ./lib/js/core
COPY --chown=node:node ./lib/js/next-core ./lib/js/next-core
COPY --chown=node:node ./lib/js/next-i18n ./lib/js/next-i18n
COPY --chown=node:node ./lib/js/next-theme ./lib/js/next-theme
COPY --chown=node:node ./lib/js/next-ui ./lib/js/next-ui
COPY --chown=node:node ./uploader/client ./uploader/client
WORKDIR /srv/workspace/phrasea/uploader/client
ENV NODE_OPTIONS=
EXPOSE 3000
CMD ["pnpm", "dev"]

############

FROM deps AS client-build

COPY --chown=node:node ./lib/js/api ./lib/js/api
COPY --chown=node:node ./lib/js/core ./lib/js/core
COPY --chown=node:node ./lib/js/next-core ./lib/js/next-core
COPY --chown=node:node ./lib/js/next-i18n ./lib/js/next-i18n
COPY --chown=node:node ./lib/js/next-theme ./lib/js/next-theme
COPY --chown=node:node ./lib/js/next-ui ./lib/js/next-ui
COPY --chown=node:node ./uploader/client ./uploader/client
WORKDIR /srv/workspace/phrasea/uploader/client
ENV NODE_OPTIONS=--max-old-space-size=4096 \
    NEXT_TELEMETRY_DISABLED=1
RUN pnpm build

############

FROM node:26.12.1-alpine3.24 AS runner

ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    HOSTNAME=0.0.0.0 \
    PORT=3000

WORKDIR /app

RUN addgroup -S app && adduser -S -G app app

COPY --from=client-build --chown=app:app /srv/workspace/phrasea/uploader/client/.next/standalone ./
COPY --from=client-build --chown=app:app /srv/workspace/phrasea/uploader/client/.next/static ./uploader/client/.next/static
COPY --from=client-build --chown=app:app /srv/workspace/phrasea/uploader/client/public ./uploader/client/public

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}

USER app
EXPOSE 3000

CMD ["node", "uploader/client/server.js"]
