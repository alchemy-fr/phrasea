FROM node:18.20.3-alpine3.20

RUN apk add --no-cache \
        python3 \
        make \
        g++ \
    && npm install -g pnpm@^9.3.0 \
    && mkdir -p /var/docker \
    && wget -q -O /var/docker/generate-env --no-verbose https://github.com/alchemy-fr/config-compiler/releases/download/v2.2.1/generate-env-alpine \
    && chmod +x /var/docker/generate-env \
    && mkdir -p /srv/workspace \
    && chown -R node: /srv/workspace

ENV NODE_OPTIONS=--openssl-legacy-provider

WORKDIR /srv/workspace

EXPOSE 3000

CMD ["/bin/sh", "-c", "NODE_OPTIONS= /var/docker/generate-env ./ && pnpm dev"]
