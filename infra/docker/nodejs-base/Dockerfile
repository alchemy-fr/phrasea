FROM node:26.12.1-alpine3.24

RUN apk add --no-cache \
        python3 \
        make \
        g++ \
        quickjs-ng \
    && npm install -g pnpm@^12.15.0 \
    && mkdir -p /srv/workspace/phrasea \
    && chown -R node: /srv/workspace/phrasea \
    && mkdir -p /docker/entrypoint.d \
    && mkdir -p /etc/app \
    && chown -R node: /etc/app

ENV NODE_OPTIONS=--openssl-legacy-provider

WORKDIR /srv/workspace/phrasea

COPY entrypoint.sh /entrypoint.sh

EXPOSE 3000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["pnpm", "dev"]
