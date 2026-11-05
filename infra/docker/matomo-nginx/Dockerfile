FROM nginx:1.30.6-alpine3.24

# brotli-libs >= 1.2.1: CVE-2026-111497
RUN apk add --no-cache 'brotli-libs>=1.2.1'

COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
