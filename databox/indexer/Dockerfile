FROM node:14.17.6-bullseye-slim

WORKDIR /usr/app

RUN chown node:node /usr/app

USER node

ENV SERVER_PORT=3000

EXPOSE 3000

COPY --chown=node:node ./lib/js /lib/js
COPY --chown=node:node ./databox/indexer .

RUN yarn install \
    && yarn build

CMD ["yarn", "start"]
