FROM maven:3.9.9-amazoncorretto-23-debian-bookworm AS spi-builder

WORKDIR /app

COPY pom.xml ./
COPY group-uuid-mapper/pom.xml ./group-uuid-mapper/
COPY jq-idp-mapper/pom.xml ./jq-idp-mapper/
RUN mvn clean package

COPY . .

RUN mvn clean package


FROM quay.io/keycloak/keycloak:26.1.2 AS builder

COPY --from=spi-builder /app/group-uuid-mapper/target/group-uuid-mapper.jar /opt/keycloak/providers/
COPY --from=spi-builder /app/jq-idp-mapper/target/jq-idp-mapper-jar-with-dependencies.jar /opt/keycloak/providers/

ENV KC_HEALTH_ENABLED=true \
    KC_DB=postgres

RUN /opt/keycloak/bin/kc.sh build \
    && mkdir /opt/keycloak/themes/phrasea

COPY themes/phrasea /opt/keycloak/themes/phrasea

FROM registry.access.redhat.com/ubi9 AS ubi-micro-build
RUN mkdir -p /mnt/rootfs
RUN dnf install --installroot /mnt/rootfs gettext --releasever 9 --setopt install_weak_deps=false --nodocs -y && \
    dnf --installroot /mnt/rootfs clean all && \
    rpm --root /mnt/rootfs -e --nodeps setup


FROM quay.io/keycloak/keycloak:26.1.2 AS keycloak

ENV KC_SPI_THEME_DEFAULT=phrasea

COPY --from=builder /opt/keycloak /opt/keycloak
COPY --from=ubi-micro-build /mnt/rootfs /

COPY entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
