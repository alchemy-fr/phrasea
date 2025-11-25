---
title: Technical Prerequisites
status: pending review
slug: prerequisite
---
# Phrasea Technical Prerequisites

## Objective

Present the technical architecture of the Phrasea software solution.

This page describes the different modules that compose it, as well as the software dependencies and external services required for its implementation.

It is intended for:

* IT departments wishing to deploy Phrasea on their infrastructure  
* Application architects seeking to integrate Phrasea  
* Technical architects seeking to integrate Phrasea  

### Phrasea Application Source

The Phrasea application source code is available on GitHub:

* [**Application Source**](https://github.com/alchemy-fr/phrasea)

### Deployment Orchestration

Phrasea can be deployed using Docker/Docker Compose or on a Kubernetes cluster. Each technology offers scalability features and has its own advantages and disadvantages. Depending on your scalability needs and infrastructure management requirements, you can select the deployment method that best suits your project.

#### Docker Compose

Enables rapid deployment of Phrasea in development or local environments. The stack includes external services such as **PostgreSQL**, **Redis**, **Elasticsearch**, and **MinIO**. With Docker Compose [**profiles**](/tech/Configuration/env_var#docker-compose), you can select which services to start, offering flexibility depending on the needs of each environment. However, stack scaling will only be vertical.

**Operating System:** Linux Ubuntu 24.04 LTS, Debian 11

**Minimum required versions:** 
  - Docker: 28.5.2  
  - Docker Compose: 2.40.3  
  
The Docker Compose file is included in the Phrasea GitHub repository to facilitate deployment in development environments:

* [**Docker Compose Deployment**](https://github.com/alchemy-fr/phrasea/blob/master/docker-compose.yml)

You can also refer to the environment variable description [page](/tech/Configuration/env_var#app_env) for information about available environment variables.

#### Kubernetes

Kubernetes is ideal for large-scale needs. Phrasea can be deployed on several nodes for horizontal scaling.
For Kubernetes deployments, use the available Helm charts:
* [**Phrasea Helm Deployment**](https://github.com/alchemy-fr/phrasea-helm-charts)

Minimum required versions:
  - Kubernetes: 1.29  
  - Helm: v3.17.1  

#### Note on Docker and Kubernetes deployments
The containers provided in **Docker Compose** and **Helm charts** for Phrasea are primarily designed for development and testing purposes. To ensure these containers are suitable for production, further customization is required. This includes optimizing configurations, securing access, managing resources, and implementing resilience and monitoring practices appropriate for production environments. These adjustments are essential before deploying to production.

It is strongly recommended to delegate **primary datastores**—such as **PostgreSQL** and S3-compatible object storage (served by **MinIO**)—to external managed services for improved performance, reliability, and scalability.

### Phrasea Docker Images

* **Dashboard**  
  - [Dashboard Docker Image](https://hub.docker.com/r/alchemyfr/ps-dashboard/tags)

* **Keycloak**  
  - [Keycloak Docker Image](https://hub.docker.com/r/alchemyfr/ps-keycloak/tags)  
  - [Configurator Docker Image](https://hub.docker.com/r/alchemyfr/ps-configurator)

* **Databox Service**  
  - [Databox PHP API](https://hub.docker.com/r/alchemyfr/ps-databox-api-php/tags)  
  - [Databox Worker](https://hub.docker.com/r/alchemyfr/ps-databox-worker/tags)  
  - [Databox Client](https://hub.docker.com/r/alchemyfr/ps-databox-client/tags)  
  - [Databox Nginx API](https://hub.docker.com/r/alchemyfr/ps-databox-api-nginx/tags)
  - Databox indexer (local build only)

* **Uploader Service**  
  - [Uploader PHP API](https://hub.docker.com/r/alchemyfr/ps-uploader-api-php/tags)  
  - [Uploader Worker](https://hub.docker.com/r/alchemyfr/ps-uploader-worker/tags)  
  - [Uploader Client](https://hub.docker.com/r/alchemyfr/ps-uploader-client/tags)  
  - [Uploader Nginx API](https://hub.docker.com/r/alchemyfr/ps-uploader-api-nginx/tags)

* **Expose Service**  
  - [Expose PHP API](https://hub.docker.com/r/alchemyfr/ps-expose-api-php/tags)  
  - [Expose Worker](https://hub.docker.com/r/alchemyfr/ps-expose-worker/tags)  
  - [Expose Client](https://hub.docker.com/r/alchemyfr/ps-expose-client/tags)  
  - [Expose Nginx API](https://hub.docker.com/r/alchemyfr/ps-expose-api-nginx/tags)

* **Zippy Service** 
   - [Zippy API](https://hub.docker.com/r/alchemyfr/zippy-api/tags)
   - [Zippy Cron](https://hub.docker.com/r/alchemyfr/zippy-cron/tags)
   - [Zippy Worker](https://hub.docker.com/r/alchemyfr/zippy-worker/tags)

  Zippy is an external service developed by Alchemy and used in Phrasea Databox and Phrasea Expose.
  Zippy is dedicated to exporting files to several destinations, e.g., downloadable zip files.
  Zippy [GitHub source repository](https://github.com/alchemy-fr/zippy-svc).  

* **Novu Notification Service**

  - [Novu-bridge](https://hub.docker.com/r/alchemyfr/ps-novu-bridge/tags) 

  For notifications sent by the application, Phrasea uses the Novu Notification service. This service is divided into two distinct parts: the backend infrastructure and the Novu bridge, which communicates with the Novu backend.

  - The Novu-bridge container must be deployed in the stack.
  - For the backend service, you can use Novu's SaaS offering  
  - or deploy your own Novu backend stack (no Helm deployment provided, only Docker Compose for development and testing).

   More information about Novu and its implementation in Phrasea [here](../../user/Databox/02_%20novu_in_phrasea.md)

* **Report Service**  
  - [Report API](https://hub.docker.com/r/alchemyfr/ps-report-api/tags)

### External Images
Images referenced in the Docker Compose and Helm stack are **not Phrasea images generated by Alchemy**, but are necessary for development or test deployments. As previously explained, these images are intended to be replaced by external services.
   
For the exact deployed version in the **Docker Compose** deployment context, refer to the environment value.
For the **Kubernetes** context, refer to [values.yaml](https://github.com/alchemy-fr/phrasea-helm-charts/blob/main/charts/phrasea/values.yaml).   

#### Network  
- **Traefik** reverse proxy and load balancer, including Let's Encrypt certification for some providers 
    - [Version and Setting](/tech/Configuration/env_var#traefik-reverse-proxy-settings)

#### Primary Datastores

- **PostgreSQL** – relational database.
    - [Version and Setting](/tech/Configuration/env_var#database-settings)

- **MinIO** – S3-compatible object storage service.
    - [Version and Setting](/tech/Configuration/env_var#s3_endpoint)

#### Search Engine, Cache Server, and Application Bus

- **Redis** – in-memory database for cache management.
    - [Version and Setting](/tech/Configuration/env_var#redis)
- **RabbitMQ** – message queue management service.
    - [Version and Setting](/tech/Configuration/env_var#rabbitmq-message-broker-settings)
- **Elasticsearch** – distributed search and analytics engine.
    - [Version and Setting](/tech/Configuration/env_var#elasticsearch-settings)
- **Soketi** – WebSocket server.
    - [Version and Setting](/tech/Configuration/env_var#soketi)

#### Additional tools useful for development and stack testing:

- **MariaDB** – relational database server used for local Matomo stack deployment.
- **PgAdmin** – management tool for PostgreSQL.
- **phpMyAdmin** – management tool for MySQL/MariaDB.
- **Mailhog** – SMTP server and mail client interface for capturing emails in development.
- **Elasticsearch-HQ** – interface for querying Elasticsearch.
- **k6** – performance load testing tool.
- **InfluxDB** – time series database used by k6.
- **Grafana** – data visualization platform for k6 test results.
- **Mendhak** – HTTP/HTTPS echo service for testing.
- **Jwilder** – Dockerize, utility for orchestrating Docker services.
- **MinIO-MC** – MinIO Client, management utility for MinIO.
- **Novu** – Novu Notification framework backend infrastructure.

## Technologies Used

### Back-end

* Programming Language:  
  * Phrasea is developed in PHP with the Symfony Framework.  
  * Node.js for certain modules (indexer)

### Front-End

* Web Technologies:  
  * HTML5, CSS3, and JavaScript form the foundation of the user interface, enabling interactive and accessible web interfaces.  
* JavaScript Framework:  
  * React and Material UI are used for clients consuming the Phrasea API.

### Database

* PostgreSQL is the relational database that stores and/or references the various Phrasea objects. It is a primary datastore; containers used in the Docker or K8s stack are only for development and testing. An external service is recommended in production.  
    The database server contains one database per service:  
     - Configurator Database  
     - Keycloak Database  
     - Databox Database  
     - Uploader Database  
     - Expose Database  
     - Report Database

### Search Engine

* Elasticsearch:  
  * To optimize search and indexing of assets/collections and scalability, Phrasea uses Elasticsearch.  
  * Denormalization of objects contained in the DBMS is performed in different indexes, allowing textual searches on relevant objects.

### File Management and Storage

* Object Storage:  
  * S3-type object storage is used to store assets and renditions. It is a primary datastore; MinIO used in the Docker or Kubernetes stack is only for development and testing. An external service and backup solution are recommended in production.  
* Block Storage: 
  * Local block storage is used for temporary files.
  * Network Attached Storage, NFS, or other shared storage solutions are used for sharing data between containers.
  

### User Management and Security

* Identity Management System (IAM)

Integration of Keycloak in Phrasea. More information [here](/user/keycloak/01_phrasea-keycloak-documentation)

### Encryption

* Data in transit is encrypted via TLS/SSL to secure communications between users and the applications.

## Architecture Diagram

![Technical Information](./technical-architecture.svg)

## Infrastructure Capacity Prerequisites

Capacity prerequisites are minimum data to be adapted according to the platform's purpose; they are given as an indication and require adaptation to the final usage context.

Metrics to consider:

- Phrasea service(s) deployed  
- Number of concurrent write users  
- Number of concurrent read users  
- Storage volume required for documents  
- Volume of document indexes  
- Number of renditions to generate  
- Expected number of versions per document  
- Number of parallel processes deployed  
- Desired SLA (Service Level Agreement)  
- Log retention period

### Development and Test

CPU: 6 CPUs  
RAM: 16 GB  
Disk capacity for Docker image management: 100 GB  
Disk capacity for temporary volumes: 100 GB

### Production

#### Phrasea images deployed with Docker Compose and Kubernetes

CPU: 6 CPUs  
RAM: 16 GB  
Disk capacity for Docker image management: 100 GB  
Disk capacity for temporary volumes: 100 GB

#### Managed Service

- PostgreSQL:

  - CPU: 2 CPUs
  - RAM: 8 GB
  - Disk size: depends on needs

- Object Storage:

  - CPU: 2 CPUs
  - RAM: 8 GB
  - Disk size: depends on needs

#### Production Considerations

* Security: Use TLS for all external traffic (configure Traefik with Let’s Encrypt or your own certificates).
* Monitoring: Set up logging and monitoring for capturing containers' standard output (e.g., Fluent Bit, Prometheus, Grafana).
* APM: You can use the [Sentry](https://sentry.io/welcome/) SaaS service for capturing stack errors; both front-end and back-end agents are installed in Phrasea's images.
  Set up your Sentry [credentials here](/tech/Configuration/env_var#php_sentry_dsn).
* Backups: Implement regular backups for PostgreSQL and object storage.
* Elasticsearch is not a primary datastore, but indexation time can be significant when the number of assets is large. Backing up Elasticsearch indexes allows for quick recovery after a failure. It is also advisable to use an external cluster for Elasticsearch.
* RabbitMQ database needs to be persisted in case of failure and container restart.
