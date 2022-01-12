FROM node:14.17.6-alpine3.12 as uploader-client-build

RUN apk add --virtual .build \
        wget \
    && mkdir -p /var/docker \
    && wget -q -O /var/docker/generate-env --no-verbose https://github.com/alchemy-fr/config-compiler/releases/download/1.0.0/generate-env-alpine \
    && chmod +x /var/docker/generate-env \
    && apk del .build \
    && mkdir -p /usr/src/app

COPY . /usr/src/app

WORKDIR /usr/src/app

RUN apk add --no-cache --virtual .gyp \
        python3 \
        make \
        g++ \
    && yarn install \
    && mv public/index.tpl.html public/index.html \
    && yarn run build \
    && apk del .gyp

EXPOSE 3000

CMD ["/bin/sh", "-c", "/var/docker/generate-env ./public && yarn start"]

############

FROM nginx:1.17.6-alpine as uploader-client-nginx

COPY --from=uploader-client-build /usr/src/app/build /var/app
COPY docker/nginx/conf.d /etc/nginx/conf.d

RUN apk add --no-cache libstdc++ \
    && apk add --virtual .build \
        wget \
    && mkdir -p /var/docker \
    && wget -q -O /var/docker/generate-env https://github.com/alchemy-fr/config-compiler/releases/download/1.0.0/generate-env-alpine \
    && chmod +x /var/docker/generate-env \
    && apk del .build \
    && rm /etc/nginx/conf.d/default.conf

EXPOSE 80

COPY config-compiler.js /var/app/

WORKDIR /var/app

CMD ["/bin/sh", "-c", "/var/docker/generate-env ./ && nginx -g 'daemon off;'"]
