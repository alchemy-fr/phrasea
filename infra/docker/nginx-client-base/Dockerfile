FROM nginx:1.30.6-alpine3.24 AS api-nginx

# brotli-libs >= 1.2.1: CVE-2026-111497
RUN apk update \
    && apk add --no-cache \
      'brotli-libs>=1.2.1' \
      quickjs-ng \
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
