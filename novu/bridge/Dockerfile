FROM node:23.10.0-alpine3.20

RUN apk add --no-cache g++ make py3-pip

ENV NX_DAEMON=false

# Install global dependencies
RUN npm --no-update-notifier --no-fund --global \
      install \
      pm2 \
      pnpm@10.6.3 \
      novu@2.6.6 \
    && pnpm --version

# Set non-root user
USER 1000

WORKDIR /usr/src/app

COPY --chown=1000:1000 package.json pnpm-lock.yaml ./

RUN pnpm install --frozen-lockfile

COPY --chown=1000:1000 . .

ENV NOVU_SECRET_KEY=__defined_me
ENV NODE_ENV=production

RUN pnpm build

CMD ["pnpm", "start"]
