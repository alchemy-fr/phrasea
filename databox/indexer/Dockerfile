FROM node:20.10.0-buster-slim

WORKDIR /srv/workspace

RUN chown -R node:node /srv/workspace \
    && npm install -g pnpm@^9.3.0

USER node

ENV SERVER_PORT=3000

COPY --chown=node:node /databox/indexer/docker/entrypoint.sh /entrypoint.sh
COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml turbo.json ./
COPY --chown=node:node ./lib/js ./lib/js
COPY --chown=node:node ./databox/indexer /srv/workspace/databox/indexer

WORKDIR /srv/workspace/databox/indexer

RUN pnpm install \
    && pnpm build \
    && chmod +x /entrypoint.sh

EXPOSE ${SERVER_PORT}

ENTRYPOINT ["/entrypoint.sh"]
