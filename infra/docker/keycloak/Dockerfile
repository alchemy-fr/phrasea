FROM maven:3.9.3-amazoncorretto-8 AS spi-builder

WORKDIR /app

COPY pom.xml ./
COPY group-uuid-pmapper/pom.xml ./group-uuid-pmapper/
COPY jq-idp-mapper/pom.xml ./jq-idp-mapper/
RUN mvn clean package

COPY . .

RUN mvn clean package


FROM quay.io/keycloak/keycloak:25.0.2 AS builder

ARG DASHBOARD_CLIENT_URL

COPY --from=spi-builder /app/group-uuid-pmapper/target/group-uuid-pmapper.jar /opt/keycloak/providers/
COPY --from=spi-builder /app/jq-idp-mapper/target/jq-idp-mapper-jar-with-dependencies.jar /opt/keycloak/providers/

ENV KC_HEALTH_ENABLED=true \
    KC_DB=postgres

RUN /opt/keycloak/bin/kc.sh build \
    && mkdir /opt/keycloak/themes/phrasea

COPY themes/phrasea /opt/keycloak/themes/phrasea

USER root

RUN sed -i "s;dashboard_url;${DASHBOARD_CLIENT_URL};g" /opt/keycloak/themes/phrasea/email/html/password-reset.ftl

RUN sed -i "s;dashboard_url;${DASHBOARD_CLIENT_URL};g" /opt/keycloak/themes/phrasea/email/text/password-reset.ftl

FROM quay.io/keycloak/keycloak:25.0.2 AS keycloak

ENV KC_SPI_THEME_DEFAULT=phrasea

COPY --from=builder /opt/keycloak /opt/keycloak


