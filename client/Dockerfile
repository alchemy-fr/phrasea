FROM node:10 as client_build

RUN mkdir -p /usr/src/app

COPY . /usr/src/app

WORKDIR /usr/src/app

RUN yarn install --silent \
    && yarn run build \
    && chmod +x ./docker/generate-env.sh

USER node

EXPOSE 3000

CMD ["/bin/sh", "-c", "./docker/generate-env.sh && mv env-config.js ./public/ && yarn start"]

############

FROM nginx:1.16.0 as client_nginx

COPY --from=client_build /usr/src/app/build /var/app
COPY docker/nginx/conf.d /etc/nginx/conf.d
COPY docker/generate-env.sh /var/docker/
COPY .env /var/.env

RUN rm /etc/nginx/conf.d/default.conf \
    && chmod +x /var/docker/generate-env.sh

EXPOSE 80

WORKDIR /var/app

CMD ["/bin/sh", "-c", "/var/docker/generate-env.sh && nginx -g 'daemon off;'"]
