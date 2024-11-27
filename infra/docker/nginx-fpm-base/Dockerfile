FROM nginx:1.17.10-alpine AS api-nginx

RUN apk add curl

COPY entrypoint.sh /entrypoint.sh
COPY tpl /etc/nginx/tpl

ENTRYPOINT ["/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]

HEALTHCHECK --start-interval=1s --start-period=20s --retries=20 --interval=60s \
	CMD wget -O /dev/null http://127.0.0.1/favicon.ico?healthcheck || exit 1

ENV UPLOAD_MAX_FILE_SIZE=5M

EXPOSE 80
