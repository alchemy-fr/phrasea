FROM cypress/included:16.2.0

RUN apt-get update \
    && apt-get install -y \
      libcanberra-gtk3-module \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /cypress

COPY cypress ./cypress
COPY cypress.config.js ./
