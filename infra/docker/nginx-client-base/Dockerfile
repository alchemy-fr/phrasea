FROM nginx:1.27.1-alpine3.20 AS api-nginx

RUN apk update \
    && apk add --no-cache \
      libstdc++ \
    && apk add --virtual .build \
        wget \
    && mkdir -p /var/docker \
    && wget -q -O /var/docker/generate-env https://github.com/alchemy-fr/config-compiler/releases/download/v2.2.1/generate-env-alpine \
    && chmod +x /var/docker/generate-env \
    && apk del .build \
    && rm /etc/nginx/conf.d/default.conf \
    && mkdir -p /docker/entrypoint.d \
    && mkdir -p /etc/app

COPY entrypoint.sh /entrypoint.sh
COPY nginx/conf.d/* /etc/nginx/conf.d/
COPY tpl /etc/nginx/tpl

EXPOSE 80

WORKDIR /var/app

ENTRYPOINT ["/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]

HEALTHCHECK --start-interval=1s --start-period=30s --retries=60 --interval=60s \
	CMD wget -O /dev/null http://127.0.0.1/index.html?healthcheck || exit 1
