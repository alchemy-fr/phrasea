ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}nodejs-base:${BASE_TAG} AS deps

COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml turbo.json ./
COPY --chown=node:node ./uploader/client-next/package.json ./uploader/client-next/package.json
# Workspace libraries (and the ones they depend on)
COPY --chown=node:node ./lib/js/api/package.json ./lib/js/api/package.json
COPY --chown=node:node ./lib/js/core/package.json ./lib/js/core/package.json

USER node
RUN pnpm install --filter "uploader-client-next..." --frozen-lockfile \
    && mkdir -p /srv/workspace/phrasea/uploader/client-next/.next

############

FROM deps AS client-dev

COPY --chown=node:node ./lib/js/api ./lib/js/api
COPY --chown=node:node ./lib/js/core ./lib/js/core
COPY --chown=node:node ./uploader/client-next ./uploader/client-next
WORKDIR /srv/workspace/phrasea/uploader/client-next
ENV NODE_OPTIONS=
EXPOSE 3000
CMD ["pnpm", "dev"]

############

FROM deps AS client-build

COPY --chown=node:node ./lib/js/api ./lib/js/api
COPY --chown=node:node ./lib/js/core ./lib/js/core
COPY --chown=node:node ./uploader/client-next ./uploader/client-next
WORKDIR /srv/workspace/phrasea/uploader/client-next
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

COPY --from=client-build --chown=app:app /srv/workspace/phrasea/uploader/client-next/.next/standalone ./
COPY --from=client-build --chown=app:app /srv/workspace/phrasea/uploader/client-next/.next/static ./uploader/client-next/.next/static
COPY --from=client-build --chown=app:app /srv/workspace/phrasea/uploader/client-next/public ./uploader/client-next/public

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}

USER app
EXPOSE 3000

CMD ["node", "uploader/client-next/server.js"]
