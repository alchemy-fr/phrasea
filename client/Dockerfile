FROM node:10 as client_build

RUN mkdir -p /usr/src/app

COPY . /usr/src/app

WORKDIR /usr/src/app

RUN yarn install --silent \
    && yarn run build

USER node

EXPOSE 3000

CMD [ "yarn", "start" ]

############

FROM nginx:1.15-alpine as client_nginx

COPY --from=client_build /usr/src/app/build /usr/share/nginx/html
COPY docker/nginx/conf.d /etc/nginx/conf.d

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
