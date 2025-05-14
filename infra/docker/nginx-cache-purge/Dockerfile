ARG NGINX_VERSION=1.27.1

FROM nginx:${NGINX_VERSION}-alpine3.20 AS builder

ENV PURGE_CACHE_VERSION=2.3

# Download sources
RUN mkdir -p /usr/src \
    && cd /usr/src \
    && wget "https://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz" -O nginx.tar.gz \
    && wget "https://github.com/FRiCKLE/ngx_cache_purge/archive/refs/tags/${PURGE_CACHE_VERSION}.tar.gz" -O ngx-cache-purge.tar.gz

# For latest build deps, see https://github.com/nginxinc/docker-nginx/blob/master/mainline/alpine/Dockerfile
RUN apk add --no-cache --virtual .build-deps \
  gcc \
  libc-dev \
  make \
  openssl-dev \
  pcre-dev \
  zlib-dev \
  linux-headers \
  curl \
  gnupg \
  libxslt-dev \
  gd-dev \
  geoip-dev

RUN cd /usr/src \
    && tar -zxC /usr/src -f nginx.tar.gz \
    && tar -xzC /usr/src -f ngx-cache-purge.tar.gz \
    && wget https://raw.githubusercontent.com/nginx-modules/ngx_cache_purge/master/config \
    && PURGE_CACHEDIR="$(pwd)/ngx_cache_purge-${PURGE_CACHE_VERSION}" \
    && mv config ${PURGE_CACHEDIR}/ \
    && cd /usr/src/nginx-$NGINX_VERSION \
    && CONFARGS=$(nginx -V 2>&1 | sed -n -e 's/^.*arguments: //p') \
    && sh -c "./configure --with-compat $CONFARGS --add-dynamic-module=$PURGE_CACHEDIR" \
    && make modules

FROM nginx:${NGINX_VERSION}-alpine3.20 AS nginx

COPY --from=builder /usr/src/nginx-${NGINX_VERSION}/objs/*_module.so /etc/nginx/modules/

RUN echo "load_module /etc/nginx/modules/ngx_http_cache_purge_module.so;" | cat - /etc/nginx/nginx.conf > /tmp/out && mv /tmp/out /etc/nginx/nginx.conf

CMD ["nginx", "-g", "daemon off;"]
