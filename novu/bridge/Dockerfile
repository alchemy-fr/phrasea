FROM node:20-alpine3.20

RUN apk add --no-cache g++ make py3-pip

ENV NX_DAEMON=false

# Install global dependencies
RUN npm --no-update-notifier --no-fund --global install pm2 pnpm@9.11.0 && \
    pnpm --version

# Set non-root user
USER 1000

WORKDIR /usr/src/app

COPY --chown=1000:1000 . .

RUN pnpm install --frozen-lockfile

CMD ["pnpm", "dev"]
