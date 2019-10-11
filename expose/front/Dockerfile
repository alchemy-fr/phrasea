FROM node:10 as expose_front_build

RUN apt-get update \
    && apt-get install -y \
        jq \
    && mkdir -p /usr/src/app /configs \
    && chown -R node: /configs \
    && rm -rf /var/lib/apt/lists/*

COPY . /usr/src/app

WORKDIR /usr/src/app

RUN yarn install --silent \
    && cp public/index.tpl.html public/index.html \
    && rm public/index.tpl.html \
    && yarn run build \
    && chmod +x ./docker/generate-env.sh

USER node

EXPOSE 3000

CMD ["/bin/sh", "-c", "./docker/generate-env.sh ./public && mv env-config.*.js ./public/ && yarn start"]

############

FROM nginx:1.16.0 as expose_front_nginx

COPY --from=expose_front_build /usr/src/app/build /var/app
COPY docker/nginx/conf.d /etc/nginx/conf.d
COPY docker/generate-env.sh /var/docker/
COPY .env /var/.env

RUN apt-get update \
    && apt-get install -y \
        jq \
    && rm -rf /var/lib/apt/lists/* \
    && rm /etc/nginx/conf.d/default.conf \
    && chmod +x /var/docker/generate-env.sh \
    && mkdir -p /usr/src/app /configs

EXPOSE 80

WORKDIR /var/app

CMD ["/bin/sh", "-c", "/var/docker/generate-env.sh ./ && nginx -g 'daemon off;'"]
