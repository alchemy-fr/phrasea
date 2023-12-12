FROM maven:3.9.3-amazoncorretto-8 as spi-builder

WORKDIR /app

COPY pom.xml ./
COPY group-uuid-pmapper/pom.xml ./group-uuid-pmapper/
COPY jq-idp-mapper/pom.xml ./jq-idp-mapper/
RUN mvn clean package

COPY . .

RUN mvn clean package


FROM quay.io/keycloak/keycloak:22.0.1 as builder

COPY --from=spi-builder /app/group-uuid-pmapper/target/group-uuid-pmapper.jar /opt/keycloak/providers/
COPY --from=spi-builder /app/jq-idp-mapper/target/jq-idp-mapper-jar-with-dependencies.jar /opt/keycloak/providers/

RUN /opt/keycloak/bin/kc.sh build \
    && mkdir /opt/keycloak/themes/phrasea

COPY themes/phrasea /opt/keycloak/themes/phrasea


FROM quay.io/keycloak/keycloak:22.0.1 as keycloak

COPY --from=builder /opt/keycloak /opt/keycloak


