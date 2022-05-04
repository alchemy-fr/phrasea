FROM nginx:1.17.10-alpine AS api-nginx

RUN apk add curl

COPY entrypoint.sh /entrypoint.sh
COPY tpl /etc/nginx/tpl

ENTRYPOINT ["/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]

ENV UPLOAD_MAX_FILE_SIZE=5M

EXPOSE 80
