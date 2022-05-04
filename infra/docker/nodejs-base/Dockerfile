FROM node:14.17.6-alpine3.12

RUN apk add --no-cache \
        python3 \
        make \
        g++ \
    && mkdir -p /var/docker \
    && wget -q -O /var/docker/generate-env --no-verbose https://github.com/alchemy-fr/config-compiler/releases/download/v2.0.0/generate-env-alpine \
    && chmod +x /var/docker/generate-env \
    && mkdir -p /usr/src/app

CMD ["/bin/sh", "-c", "/var/docker/generate-env ./public && yarn start"]
WORKDIR /usr/src/app
EXPOSE 3000
