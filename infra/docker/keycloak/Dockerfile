FROM maven:3.9.3-amazoncorretto-8 as spi-builder

WORKDIR /app

COPY pom.xml ./
COPY group-uuid-pmapper/pom.xml ./group-uuid-pmapper/
COPY jq-idp-mapper/pom.xml ./jq-idp-mapper/
RUN mvn clean package

COPY . .
RUN ls -la


RUN mvn clean package


FROM registry.access.redhat.com/ubi9 AS jq-builder

ENV ONIGURUMA_VERSION=5.9.6 \
    JQ_VERSION=1.6

RUN dnf install -y wget
RUN dnf install -y g++
RUN mkdir -p /jq/build \
    && cd /jq/build \
    && PLATFORM=$(printf "$(uname)-$(uname -m)" | tr "[A-Z]" "[a-z]") \
      wget https://github.com/kkos/oniguruma/releases/download/v${ONIGURUMA_VERSION}/onig-${ONIGURUMA_VERSION}.tar.gz \
    && tar xvf onig-${ONIGURUMA_VERSION}.tar.gz \
    && rm onig-${ONIGURUMA_VERSION}.tar.gz \
    && cd onig-${ONIGURUMA_VERSION} \
    && ./configure --prefix $(cd .. && pwd -P) \
    && make \
    && make install \
    && cd .. \
    && wget https://github.com/stedolan/jq/releases/download/jq-${JQ_VERSION}/jq-${JQ_VERSION}.tar.gz \
    && tar xvf jq-${JQ_VERSION}.tar.gz \
    && rm jq-${JQ_VERSION}.tar.gz \
    && cd jq-${JQ_VERSION} \
    && ./configure --disable-maintainer-mode --prefix $(cd .. && pwd -P) --with-oniguruma=$(cd .. && pwd -P) --disable-docs \
    && sed -i.bak 's/LIBS = -lonig/LIBS = /' Makefile \
    && sed -i.bak "s/libjq_la_LIBADD = -lm/libjq_la_LIBADD = -lm $(find ../onig-${ONIGURUMA_VERSION} -name '*.lo' | xargs echo | sed 's/\//\\\//g')/" Makefile \
    && sed -i.bak "s/jq_LDADD = libjq.la -lm/jq_LDADD = libjq.la -lm $(find ../onig-${ONIGURUMA_VERSION} -name '*.lo' | xargs echo | sed 's/\//\\\//g')/" Makefile \
    && make \
    && make install \
    && cd ..

FROM quay.io/keycloak/keycloak:21.1.2 as builder

COPY --from=jq-builder jq/build /opt/jq
COPY --from=spi-builder /app/group-uuid-pmapper/target/group-uuid-pmapper.jar /opt/keycloak/providers/
COPY --from=spi-builder /app/jq-idp-mapper/target/jq-idp-mapper.jar /opt/keycloak/providers/

RUN ls -la /opt/jq/bin/jq
RUN echo $PATH

USER root
RUN ln -s /opt/jq/bin/jq /usr/local/bin/
USER keycloak

RUN jq --help
RUN /opt/keycloak/bin/kc.sh build


FROM quay.io/keycloak/keycloak:21.1.2 as keycloak

COPY --from=builder /opt/keycloak /opt/keycloak


