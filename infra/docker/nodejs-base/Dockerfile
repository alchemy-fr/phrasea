FROM node:18.15.0-alpine3.17

RUN apk add --no-cache \
        python3 \
        make \
        g++ \
    && mkdir -p /var/docker \
    && wget -q -O /var/docker/generate-env --no-verbose https://github.com/alchemy-fr/config-compiler/releases/download/v2.2.1/generate-env-alpine \
    && chmod +x /var/docker/generate-env \
    && mkdir -p /srv/app

ENV NODE_OPTIONS=--openssl-legacy-provider

CMD ["/bin/sh", "-c", "NODE_OPTIONS= /var/docker/generate-env ./public && NODE_OPTIONS=--openssl-legacy-provider yarn start"]
WORKDIR /srv/app
EXPOSE 3000
