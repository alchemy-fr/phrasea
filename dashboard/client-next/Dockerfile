ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}nodejs-base:${BASE_TAG} AS deps

COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml turbo.json ./
COPY --chown=node:node ./dashboard/client-next/package.json ./dashboard/client-next/package.json

USER node
RUN pnpm install --filter "dashboard-client-next..." --frozen-lockfile \
    && mkdir -p /srv/workspace/phrasea/dashboard/client-next/.next

############

FROM deps AS client-dev

COPY --chown=node:node ./dashboard/client-next ./dashboard/client-next
WORKDIR /srv/workspace/phrasea/dashboard/client-next
ENV NODE_OPTIONS=
EXPOSE 3000
CMD ["pnpm", "dev"]

############

FROM deps AS client-build

COPY --chown=node:node ./dashboard/client-next ./dashboard/client-next
WORKDIR /srv/workspace/phrasea/dashboard/client-next
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

COPY --from=client-build --chown=app:app /srv/workspace/phrasea/dashboard/client-next/.next/standalone ./
COPY --from=client-build --chown=app:app /srv/workspace/phrasea/dashboard/client-next/.next/static ./dashboard/client-next/.next/static
COPY --from=client-build --chown=app:app /srv/workspace/phrasea/dashboard/client-next/public ./dashboard/client-next/public
COPY --from=client-build --chown=app:app /srv/workspace/phrasea/dashboard/client-next/build-info ./dashboard/client-next/build-info

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}

USER app
EXPOSE 3000

CMD ["node", "dashboard/client-next/server.js"]
