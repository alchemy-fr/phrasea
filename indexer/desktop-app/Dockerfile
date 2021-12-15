FROM node:14.17.6-bullseye-slim

RUN apt-get update \
    && apt-get -y install \
      bison \
      build-essential \
      clang \
      curl \
      g++-multilib \
      gcc-multilib \
      gperf \
      libasound2-dev \
      libcap-dev \
      libcups2-dev \
      libdbus-1-dev \
      libgconf2-dev \
      libgtk-3-dev \
      libgtkextra-dev \
      libnotify-dev \
      libnss3-dev \
      libxss1 \
      libxtst-dev \
    && update-ca-certificates

WORKDIR /usr/app

ENV NODE_ENV=development

COPY . .

RUN yarn install \
    && yarn package \
    && chown root:root /usr/app/node_modules/electron/dist/chrome-sandbox \
    && chmod 4755 /usr/app/node_modules/electron/dist/chrome-sandbox

USER node

CMD ["yarn", "start"]
