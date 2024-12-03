ARG ELK_VERSION

# https://www.docker.elastic.co/
FROM docker.elastic.co/logstash/logstash:${ELK_VERSION}

RUN logstash-plugin install logstash-integration-jdbc

RUN mkdir /opt/logstash/vendor/jdbc \
    && mkdir /usr/share/logstash/files \
    && curl -Lo /opt/logstash/vendor/jdbc/postgresql.jar https://jdbc.postgresql.org/download/postgresql-42.2.18.jar

COPY ./config/logstash.yml /usr/share/logstash/config/logstash.yml
COPY ./files/report.sql /usr/share/logstash/files/report.sql
COPY ./pipeline/logstash.conf /usr/share/logstash/pipeline/logstash.conf

ENV ELASTICSEARCH_HOST=elasticsearch-report:9200
