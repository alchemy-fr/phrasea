FROM maven:3.9.9-amazoncorretto-23-debian-bookworm AS spi-builder

WORKDIR /app

COPY pom.xml ./
COPY group-uuid-mapper/pom.xml ./group-uuid-mapper/
COPY jq-idp-mapper/pom.xml ./jq-idp-mapper/
RUN mvn clean package

COPY . .

RUN mvn clean package


FROM quay.io/keycloak/keycloak:26.6.2 AS builder

COPY --from=spi-builder /app/group-uuid-mapper/target/group-uuid-mapper.jar /opt/keycloak/providers/
COPY --from=spi-builder /app/jq-idp-mapper/target/jq-idp-mapper-jar-with-dependencies.jar /opt/keycloak/providers/

ENV KC_HEALTH_ENABLED=true \
    KC_DB=postgres

RUN /opt/keycloak/bin/kc.sh build \
    && mkdir /opt/keycloak/themes/phrasea

COPY themes/phrasea /opt/keycloak/themes/phrasea


FROM quay.io/keycloak/keycloak:26.6.2 AS keycloak

ENV KC_SPI_THEME_DEFAULT=phrasea

COPY --from=builder /opt/keycloak /opt/keycloak

USER root

RUN chown -R 1000:1000 /opt/keycloak/themes/phrasea

USER 1000

COPY entrypoint.sh /entrypoint.sh

HEALTHCHECK --start-interval=2s --start-period=90s --retries=100 --interval=60s --timeout=2s \
    CMD { printf 'HEAD /health/ready HTTP/1.0\r\n\r\n' >&0; grep 'HTTP/1.0 200'; } 0<>/dev/tcp/localhost/9000

ENTRYPOINT ["/entrypoint.sh"]
