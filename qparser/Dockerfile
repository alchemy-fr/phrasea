FROM nginx:1.17.9-alpine

COPY docker/root /
COPY src /var/app
COPY src/public /usr/share/nginx/html

ENTRYPOINT ["/entrypoint.sh"]

CMD ["nginx", "-g", "daemon off;"]
