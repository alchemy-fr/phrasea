FROM cypress/included:15.8.1

RUN apt-get update \
    && apt-get install -y \
      libcanberra-gtk3-module \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /cypress

COPY cypress ./cypress
COPY cypress.config.js ./
