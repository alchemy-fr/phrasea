ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}nodejs-base:${BASE_TAG} AS deps

COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml turbo.json ./
COPY --chown=node:node ./databox/client-nextjs/package.json ./databox/client-nextjs/package.json

USER node
RUN pnpm install --filter databox-client-nextjs --frozen-lockfile \
    && mkdir -p /srv/workspace/phrasea/databox/client-nextjs/.next

############

FROM deps AS client-dev

COPY --chown=node:node ./databox/client-nextjs ./databox/client-nextjs
WORKDIR /srv/workspace/phrasea/databox/client-nextjs
ENV NODE_OPTIONS=
EXPOSE 3000
CMD ["pnpm", "dev"]

############

FROM deps AS client-build

COPY --chown=node:node ./databox/client-nextjs ./databox/client-nextjs
WORKDIR /srv/workspace/phrasea/databox/client-nextjs
ENV NODE_OPTIONS=--max-old-space-size=4096 \
    NEXT_TELEMETRY_DISABLED=1
RUN pnpm build

############

FROM node:25.2.1-alpine3.22 AS runner

ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    HOSTNAME=0.0.0.0 \
    PORT=3000

WORKDIR /app

RUN addgroup -S app && adduser -S -G app app

COPY --from=client-build --chown=app:app /srv/workspace/phrasea/databox/client-nextjs/.next/standalone ./
COPY --from=client-build --chown=app:app /srv/workspace/phrasea/databox/client-nextjs/.next/static ./databox/client-nextjs/.next/static
COPY --from=client-build --chown=app:app /srv/workspace/phrasea/databox/client-nextjs/public ./databox/client-nextjs/public

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}

USER app
EXPOSE 3000

CMD ["node", "databox/client-nextjs/server.js"]
