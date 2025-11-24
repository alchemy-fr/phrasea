---
title: phrasea technical Prerequisites
status: WIP (Work In Progress)
slug: prerequisite
---
# Phrasea Technical Prerequisites

## Objective

Present the technical architecture of the Phrasea software solution.

This Pages describes the different modules that compose it, as well as the software dependencies and external services required for its implementation.

It is intended for:

* IT departments wishing to deploy Phrasea on their infrastructure  
* Application architects of projects wanting to integrate Phrasea  
* Technical architects of projects wanting to integrate Phrasea  

### Phrasea Application Source

The Phrasea application source code is available on GitHub:

* [**Application Source**](https://github.com/alchemy-fr/phrasea)

### Deployment Orchestration

Phrasea can be deployed using Docker/Docker Compose or on a Kubernetes cluster, each technology having its own advantages and disadvantages but offering scalability features. Depending on your scalability needs and infrastructure management requirements, you can select the deployment method that best suits your project.


#### Docker Compose

Allows rapid deployment of Phrasea in development or local environments. The stack includes external services such as **PostgreSQL**, **Redis**, **Elasticsearch**, and **Minio**. with Docker Compose **profiles**, you can select which services to start, offering  flexibility depending on the needs of each environment, but the stack scaling will be only in vertical mode.

Operating System: Linux (recommended: Ubuntu 22.04 LTS, Debian 11, or CentOS 7/8)

Minimal requisited version: 
  - docker version: 28.5.2  
  - docker compose: 2.40.3  
  
  the Docker Compose file is included in the Phrasea GitHub repository to facilitate deployment in development environments:

  * [**Docker Compose Deployment**](https://github.com/alchemy-fr/phrasea/blob/master/docker-compose.yml)

  You can also refer to env description [page](/tech/Configuration/env_var#app_env). for information about availlable 

#### Kubernetes

Kubernetes is ideal for large-scale needs. In this case, Phrasea can be deployed on several nodes for horizontal scaling.
For Kubernetes deployments use the available Helm charts:
* [**Phrasea Helm Deployment**](https://github.com/alchemy-fr/phrasea-helm-charts)

Minimal requisited version:
  - Kubernetes version: 1.29  
  - Helm Version: v3.17.1  

#### Note on Docker and Kubernetes deployments
The containers provided in **Docker Compose** and **Helm charts** for Phrasea are primarily designed for development and testing purposes. To ensure these containers are suitable for production, further customization is required. This includes optimizing configurations, securing access, managing resources, and implementing resilience and monitoring practices appropriate for production environments. These adjustments are essential before deploying to production.

It is strongly recommended to delegate **primary datastores**—such as **PostgreSQL** and S3-compatible object storage (served by **Minio**)—to external managed services for improved performance, reliability, and scalability.

### List of Phrasea Docker Images

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

* **Novu Notification Service**

  - [Novu-bridge](https://hub.docker.com/r/alchemyfr/ps-novu-bridge/tags) 

  For notifications sent by the application, Phrasea uses the Novu Notification service. This service is divided into two distinct parts: the backend infrastructure and the Novu bridge, which communicates with the Novu backend.

  - The Novu-bridge container must be deployed in the stack.
  - For the backend service, you can use Novu's SaaS offering  
  - or deploy your own Novu backend stack (no Helm deployment provided, only Docker Compose for development and testing).

   more informations about Novu and implementation made in Phrasea [here](../../user/Databox/02_%20novu_in_phrasea.md)

* **Report Service**  
  - [Report API](https://hub.docker.com/r/alchemyfr/ps-report-api/tags)

### External Images  
   Images referenced in the Docker Compose and Helm stack are **not Phrasea images generated by Alchemy**, but are necessary for development or test deployments. As previously explained, these images are intended to be replaced by external services.
  
  #### Network  
  - **.5.5** – Traefik, a reverse proxy and load balancer including Let's Encrypt certification for certain providers

  #### Primary Datastores

  - **postgres:14.4-alpine** – PostgreSQL, relational database.
  - **minio/minio**  **.2021-11-24T23-19-33Z.hotfix.1d85a4563** – MinIO, S3-compatible object storage service.

  #### Cache Server and Bus

  - **redis:5.0.5-alpine** – Redis, in-memory database for cache management.
  - **rabbitmq:3.7.14-management** – RabbitMQ, message queue management service.
  - **elasticsearch:7.17.3** – Elasticsearch, distributed search and analytics engine.
  - **quay.io/soketi/soketi:330e1a60197d2b5798a3b3a2bcd211ec124148d8-16-alpine** – Soketi, a WebSocket server.

  #### Additional tools usefull for development and stack testing:

  - **mariadb:10.4.10-bionic** – MariaDB, relational database server used for local Matomo stack deployment.
  - **dpage/pgadmin4:8.6** – PgAdmin, management tool for PostgreSQL.
  - **phpmyadmin/phpmyadmin** – PhpMyAdmin, management tool for MySQL/MariaDB.
  - **mailhog/mailhog** – MailHog, SMTP server for capturing emails in development.
  - **elastichq/elasticsearch-hq** – Elasticsearch HQ, interface for querying Elasticsearch.
  - **grafana/k6:0.26.2** – k6, performance load testing tool.
  - **influxdb:1.8** – InfluxDB, time series database.
  - **grafana/grafana:8.4.2** – Grafana, data visualization platform.
  - **mendhak/http-https-echo:23** – HTTP/HTTPS echo service for testing.
  - **jwilder/dockerize:0.6.1** – Dockerize, utility for orchestrating Docker services.
  - **minio/mc .2020-09-18T00-13-21Z** – MinIO Client, management utility for MinIO.
  - **Novu** back-end infrastructures

## Technologies Used

### Back-end

* Programming Language:  
  * Phrasea is developed in PHP with the Symfony Framework.  
  * NodeJs for certain modules (indexer)

### Front-End

* Web Technologies:  
  * HTML5, CSS3, and JavaScript form the foundation of the user interface, enabling interactive and accessible web interfaces.  
* JavaScript Framework:  
  * React and Material UI are used for clients consuming the Phrasea API.

### Database

* Relational DBMS:  
  * PostgreSQL is the relational database that stores and/or references the various Phrasea objects. It is a primary datastore; containers used in the Docker or K8s stack are only for development and testing, an external service is recommended in production.  
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
  * S3-type object storage is used to store assets and renditions. It is a primary datastore; Minio used in the Docker or K8s stack is only for development and testing, an external service and backup solution is recommended in production.  
  * Network Attached Storage, NFS, or other shared storage solutions are used for local storage of temporary files.

### User Management and Security

* Identity Management System (IAM)

Integration of Keycloak in Phrasea

Authentication and Authorization

* Keycloak supports user authentication via standard protocols like OAuth2, OpenID Connect, and SAML. This facilitates integration with web, mobile applications, and APIs.  
* Keycloak allows role-based authorization management, offering granular user permission management, which can be aligned with roles defined in the Phrasea database (administrators, editors, viewers, etc.).

User and Group Management

* Keycloak manages users and groups, allowing segmentation based on needs. Each user can be assigned to one or more groups, and permissions can be attributed to these groups.  
* User information (such as email addresses, usernames, and roles) can be synchronized with Phrasea, ensuring a smooth and centralized user experience.

Single Sign-On (SSO)

* Keycloak provides SSO, allowing users to log in once to access all Phrasea-related applications, both internal and external. This improves user experience and simplifies session management.  
* User sessions can be managed centrally, and Keycloak also allows session revocation if needed (e.g., when a user leaves the organization).

Federation and Integration with External IdPs

* Keycloak can connect to external identity providers (IdP) such as Microsoft Active Directory, or other SAML and OpenID Connect compatible providers. This allows using existing identities without creating new accounts for Phrasea.  
* Federation simplifies authentication for organizations already using other IAM systems.

Advanced Security Features

* Multi-factor authentication (MFA): Keycloak supports two-factor authentication, enhancing security by requiring a second verification (such as a mobile app or token).  
* Session and token management: Keycloak allows configuring session and token lifetimes and can force token revocation in case of compromise.  
* Audit and Logs: Keycloak provides logging capabilities to track logins, authentication failures, and other security events, which is essential for compliance requirements.

Configuration

* When deploying the stack, Phrasea runs a configurator that sets up a realm and clients dedicated to this installation. These clients are used by the various Phrasea services.  
* A root Keycloak account and a master admin account for the application are also initialized.  
* Interconnections with the client's IdP are declared manually at a later stage.  
* Phrasea can be configured with OAuth2 or OpenID Connect for user authentication, centralizing identity management and facilitating integration with third-party providers.

Encryption

* Data in transit is encrypted via TLS/SSL to secure communications between users and the application.

## 2. Phrasea Architecture Diagram

![Technical Information](./technical-architecture.svg)

## 3. Infrastructure Capacity Prerequisites

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

CPU: 6 CPU  
RAM: 16 GB  
Disk capacity for Docker image management: 100 GB  
Disk capacity for temporary volumes: 100 GB

### Production

#### Phrasea images deployed with Docker Compose and/or Helm

CPU: 6 CPU  
RAM: 16 GB  
Disk capacity for Docker image management: 100 GB  
Disk capacity for temporary volumes: 100 GB

#### Managed Service

- PostgreSQL:

  - CPU: 2 CPU
  - RAM: 8 GB
  - Disk: 50 GB

- Object Storage:

  - not applicable

#### Production Considerations

Security: Use TLS for all external traffic (configure Traefik with Let’s Encrypt or your own certificates).
Monitoring: Set up logging and monitoring for containers (e.g., Prometheus, Grafana).
Backups: Implement regular backups for PostgreSQL and object storage.