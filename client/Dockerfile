FROM node:10 as client_build

ARG UPLOAD_BASE_URL=http://localhost:8080
ARG DEV_MODE=true
ARG CLIENT_ID
ARG CLIENT_SECRET

RUN mkdir -p /usr/src/app

COPY . /usr/src/app

WORKDIR /usr/src/app

ENV REACT_APP_UPLOAD_BASE_URL=$UPLOAD_BASE_URL
ENV REACT_APP_DEV_MODE=$DEV_MODE
ENV REACT_APP_CLIENT_ID=$CLIENT_ID
ENV REACT_APP_CLIENT_SECRET=$CLIENT_SECRET

RUN yarn install --silent \
    && yarn run build

USER node

EXPOSE 3000

CMD [ "yarn", "start" ]

############

FROM nginx:1.15-alpine as client_nginx

COPY --from=client_build /usr/src/app/build /var/app
COPY docker/nginx/conf.d /etc/nginx/conf.d

RUN rm /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
