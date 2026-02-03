FROM node:25.2.1-alpine3.22

RUN apk add --no-cache \
        python3 \
        make \
        g++ \
    && npm install -g pnpm@^10.28.2 \
    && mkdir -p /var/docker \
    && wget -q -O /var/docker/generate-env --no-verbose https://github.com/alchemy-fr/config-compiler/releases/download/v2.2.1/generate-env-alpine \
    && chmod +x /var/docker/generate-env \
    && mkdir -p /srv/workspace \
    && chown -R node: /srv/workspace \
    && mkdir -p /docker/entrypoint.d \
    && mkdir -p /etc/app \
    && chown -R node: /etc/app

ENV NODE_OPTIONS=--openssl-legacy-provider

WORKDIR /srv/workspace

COPY entrypoint.sh /entrypoint.sh
COPY entrypoint.d/* /docker/entrypoint.d/

EXPOSE 3000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["pnpm", "dev"]
