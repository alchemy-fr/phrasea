FROM node:20-alpine3.20

RUN apk add --no-cache g++ make py3-pip

ENV NX_DAEMON=false

# Install global dependencies
RUN npm --no-update-notifier --no-fund --global install pm2 pnpm@9.11.0 && \
    pnpm --version

RUN mkdir -p /usr/src/app \
    && chown -R 1000:1000 /usr/src/app

# Set non-root user
USER 1000

EXPOSE 2022

WORKDIR /usr/src/app

COPY --chown=1000:1000 ./novu .

RUN (cd ./packages/novu && pnpm install)
RUN pnpm install
RUN (cd ./packages/novu && pnpm build)

CMD ["sh", "-c", "pnpm exec ./packages/novu/dist/src/index.js dev --headless --dashboard-url ${NOVU_DASHBOARD_URL} --origin ${NOVU_BRIDGE_URL} --tunnel ${NOVU_BRIDGE_URL} --studio-host=0.0.0.0"]
