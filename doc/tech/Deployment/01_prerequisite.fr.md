---
title: Pré-requis techniques Phrasea
status: WIP (Rédaction en cours)
slug: prerequisite
---
# Pré-requis techniques.

# 1. Objectif

Présenter l'architecture technique de la solution logicielle Phrasea.

cette section décrit les différents modules qui la composent, ainsi que les dépendances logicielles et les services externes nécessaires à son deployement.

elle s’adresse aux personnes suivantes :

* Les services informatiques désireux de déployer Phrasea sur leur infrastructure.  
* Les architectes applicatifs des projets désirant intégrer Phrasea.  
* Les architectes techniques des projets désirant intégrer Phrasea. 

  1. ## Déploiement 

     2. ### Docker Compose 

        Permet de déployer rapidement Phrasea dans des environnements de développement ou locaux. La stack inclut des services externes tels que **PostgreSQL**, **Redis**, **Elasticsearch**, et **Minio**. Grâce à la fonctionnalité des **profils** dans Docker Compose, il est possible de sélectionner quels services démarrer, offrant ainsi une grande flexibilité selon les besoins de chaque environnement.


     3. ### Kubernetes

        Kubernetes est idéal pour les déploiements à grande échelle et en production. Phrasea utilise Kubernetes pour orchestrer les conteneurs et permet également de déléguer certaines parties de la stack à des services externes. Il est fortement recommandé de déléguer les **datastores primaires**, comme **PostgreSQL** et le stockage objet de type S3 (servi par **Minio**), à des services externes pour une meilleure gestion des performances et de la résilience.


     4. ### Modération au sujet des déploiements docker et kubernetes

        Les conteneurs utilisés dans le **Docker Compose** et les **charts Helm** fournis avec Phrasea sont principalement destinés aux environnements de développement et de test. Une personnalisation supplémentaire est nécessaire pour rendre ces conteneurs **"production ready"**. Cela inclut l'optimisation des configurations, la sécurisation des accès, la gestion des ressources, et la mise en place de pratiques de résilience et de monitoring adaptées à la production. Il est donc essentiel d'effectuer ces ajustements avant tout déploiement en production.

        En fonction de vos exigences en matière de scalabilité et de gestion d'infrastructure, vous pouvez choisir la méthode qui correspond le mieux à votre projet.


     5. ### Source de l'application Phrasea

      Le code source de l'application Phrasea est disponible sur GitHub :

      * [**Sources de l’application**](https://github.com/alchemy-fr/phrasea)


     6. ### Déploiement avec Docker Compose

       Un fichier Docker Compose est disponible pour faciliter le déploiement de Phrasea dans des environnements de développement :

        * [**Docker Compose déploiement**](https://github.com/alchemy-fr/phrasea/blob/master/docker-compose.yml)


      7. ### Déploiement avec Helm

        Pour des déploiements en production sur Kubernetes, vous pouvez utiliser les charts Helm disponibles :

        * [**Phrasea Helm déploiement**](https://github.com/alchemy-fr/alchemy-helm-charts-repo/tree/main/charts/phrasea)


      8. ### Liste des images Docker Phrasea

     

        * **Dashboard**  
          [Image Docker du Dashboard](https://hub.docker.com/repository/docker/alchemyfr/ps-dashboard/tags)


        * **Keycloak**  
          [Image Docker de Keycloak](https://hub.docker.com/repository/docker/alchemyfr/ps-keycloak/tags)

        * **Configurator**
          [Image Docker du Configurator](https://hub.docker.com/repository/docker/alchemyfr/ps-configurator)


        * **Service Databox**  
          [API PHP Databox](https://hub.docker.com/r/alchemyfr/ps-databox-api-php/tags)  
          [Worker Databox](https://hub.docker.com/r/alchemyfr/ps-databox-worker/tags)  
          [Client Databox](https://hub.docker.com/repository/docker/alchemyfr/ps-databox-client/tags)  
          [API Nginx Databox](https://hub.docker.com/repository/docker/alchemyfr/ps-databox-api-nginx/tags)

        **Databox indexer** (build local uniquement)


        * **Service Uploader**  
          [API PHP Uploader](https://hub.docker.com/r/alchemyfr/ps-uploader-api-php/tags)  
          [Worker Uploader](https://hub.docker.com/r/alchemyfr/ps-uploader-worker/tags)  
          [Client Uploader](https://hub.docker.com/repository/docker/alchemyfr/ps-uploader-client/tags)  
          [API Nginx Uploader](https://hub.docker.com/repository/docker/alchemyfr/ps-uploader-api-nginx/tags)

        * **Service Expose**  
          [API PHP Expose](https://hub.docker.com/r/alchemyfr/ps-expose-api-php/tags)  
          [Worker Expose](https://hub.docker.com/r/alchemyfr/ps-expose-worker/tags)  
          [Client Expose](https://hub.docker.com/repository/docker/alchemyfr/ps-expose-client/tags)  
          [API Nginx Expose](https://hub.docker.com/repository/docker/alchemyfr/ps-expose-api-nginx/tags)


       * **Service de notification Novu**

          [Novu-bridge](https://hub.docker.com/r/alchemyfr/ps-novu-bridge/tags) 

          service de notification en SAAS ou hosté localement
          Le container Novu-bridge est obligatoirement déployé sur la stack. 

            - Pour le service Backend, il est possible d’utiliser le service saas de Novu  
            - ou de déployer une stack Novu Backend, pas de déploiement Helm fourni, uniquement du docker      compose en mode développement. Aucune information d’authentification ne transite par cette stack.  
                

    
        * **Service Report**  
          [API Report](https://hub.docker.com/repository/docker/alchemyfr/ps-report-api/tags)

          1. **autres containers**  
            Images référencées dans la stack Docker Compose et Helm qui **ne sont pas des images Phrasea, généré par alchemy,** elles sont toutefois nécessaire lors d’un déploiement de développement ou de test et comme expliqué précédemment ces images sont destiné a être remplacé par des services extérieures.  
          2. Mise en réseaux   
            3. **.5.5** – Traefik, un reverse proxy et load balancer incluant la mécanique de certification let’s encrypt pour certains provider

        Magasin de Données Primaires.    

            4. **postgreSQL:14.4-alpine** – PostgreSQL,  base de données relationnelle.  
            5. **minio/minio**  **.2021-11-24T23-19-33Z.hotfix.1d85a4563** – MinIO, un service de stockage objet compatible S3.

        Serveur de cache et Bus de données

      6. **redis:5.0.5-alpine** – Redis, une base de données en mémoire pour la gestion de cache.  
      7. **rabbitmq:3.7.14-management** – RabbitMQ, service de gestion de files d'attente de messages.  
      8. **elasticsearch:7.17.3** – Elasticsearch, Moteur de recherche et d'analyse distribué.  
      9. **quay.io/soketi/soketi:330e1a60197d2b5798a3b3a2bcd211ec124148d8-16-alpine** – Soketi, un serveur WebSocket.

          Outils complémentaire  la stack de développement et de test :

      10. **mariadb:10.4.10-bionic** – MariaDB, un serveur de base de données relationnelle utilisé lors du déploiement de la stack Matomo en locale  .  
      11. **dpage/pgadmin4:8.6** – PgAdmin, un outil de gestion pour PostgreSQL.  
      12. **phpmyadmin/phpmyadmin** – PhpMyAdmin, un outil de gestion pour MySQL/MariaDB.  
      13. **mailhog/mailhog** – MailHog, un serveur SMTP pour capturer les emails en développement.  
      14. **elastichq/elasticsearch-hq** – Elasticsearch HQ, une interface pour requeter  Elasticsearch.  
      15. **grafana/k6:0.26.2** – k6, un outil de test de charge pour les performances.  
      16. **influxdb:1.8** – InfluxDB, une base de données de séries temporelles.  
      17. **grafana/grafana:8.4.2** – Grafana, une plateforme de visualisation de données.  
      18. **mendhak/http-https-echo:23** – Un service HTTP/HTTPS d'écho pour le testing.  
      19. **jwilder/dockerize:0.6.1** – Dockerize, un utilitaire pour orchestrer les services Docker.  
      20. **minio/mc .2020-09-18T00-13-21Z** – MinIO Client, un utilitaire de gestion pour MinIO.  
      21. **Novu** back-end infrastructures

    2. ## Technologies utilisées

       1. ### Back-end

    * Langage de Programmation :  
      * Phrasea est développé en PHP avec le Framework Symfony.  
      * NodeJs pour certain module (indexeur)

        

    2. ### Front-End

    * Technologies Web :  
      * HTML5, CSS3, et JavaScript sont les bases de l’interface utilisateur, permettant de créer des interfaces web interactives et accessibles.  
    * Framework JavaScript :  
      * Material UI et  React pour les clients consommant l’api Phrasea.

    3. ### Base de Données

    * SGBD Relationnel :  
      * PostgreSQL la bases de données relationnelles stocke  et/ou référence les différents objets Phrasea, c’est un primary datastore, à ce titre les containers utilisés dans la stack docker ou K8s ne sont là que pour développement et test, un service externe est recommandé en production.  
        le serveur de base de données contient une  bases de données par services   
        Base de Données Configurator  
        Base de données Keycloack  
        Base de données databox  
        Base de données Uploader  
        Base de données de Expose  
        Base de Données report

      4. ### Moteur de Recherche

    * Elasticsearch :  
      * Pour optimiser la recherche et l'indexation des assets/collection et la scalabilité Phrasea utilise  Elasticsearch.  
      * Une dé-normalisation des objets contenus dans le SGBD est effectuée dans différents index, permettant des recherches textuelles sur les objets le nécessitant.

      5. ### Gestion des Fichiers et Stockage

    * Stockage d'Objets :  
      * Object storage type S3  est utilisé pour stocker les assets et les renditions, c’est un primary datastore, à ce titre Minio utilisés dans la stack docker ou K8s n’est la que pour développement et test, un service externe et sa solution de sauvegarde est recommandé en production.  
      * Network Attached Storage, NFS ou d'autres solutions de stockage partagées est employées pour un stockage local des fichiers temporaires.

      ### 

      6. ### Gestion des Utilisateurs et Sécurité

    * Système de Gestion des Identités (IAM) 

    Intégration de Keycloak dans Phrasea

      Authentification et Autorisation

    * Keycloak prend en charge l'authentification des utilisateurs via des protocoles standards comme OAuth2, OpenID Connect, et SAML. Cela facilite l'intégration avec des applications web, mobiles et des API.  
    * Keycloak permet de gérer les autorisations par rôle, offrant ainsi une gestion granulaire des permissions des utilisateurs, qui peut être alignée avec les rôles définis dans la base de données Phrasea (administrateurs, éditeurs, visionneurs, etc.).

      Gestion des Utilisateurs et des Groupes

    * Keycloak permet de gérer des utilisateurs et des groupes, ce qui permet de segmenter les utilisateurs en fonction de leurs besoins. Chaque utilisateur peut être assigné à un ou plusieurs groupes, et les permissions peuvent être attribuées à ces groupes.  
    * Les informations sur les utilisateurs (comme les adresses e-mail, les noms d'utilisateur, et les rôles) peuvent être synchronisées avec Phrasea, garantissant une expérience utilisateur fluide et centralisée.

      Single Sign-On (SSO)

    * Keycloak fournit le SSO, permettant aux utilisateurs de se connecter une seule fois pour accéder à toutes les applications liées à Phrasea, tant en interne qu’en externe. Cela améliore l'expérience utilisateur et simplifie la gestion des sessions.  
    * Les sessions utilisateur peuvent être gérées de manière centralisée, et Keycloak permet également la révocation de sessions en cas de besoin (par exemple, si un utilisateur quitte l'organisation).

      Fédération et Intégration avec les IdP Externes

    * Keycloak peut se connecter à des fournisseurs d'identité externes (IdP) tels que Microsoft Active Directory, ou d’autres fournisseurs compatibles SAML et OpenID Connect. Cela permet d'utiliser des identités existantes sans créer de nouveaux comptes pour Phrasea.  
    * La fédération simplifie l'authentification pour les organisations utilisant déjà d'autres systèmes IAM.

      Fonctionnalités de Sécurité Avancées

    * Authentification à plusieurs facteurs (MFA) : Keycloak prend en charge l'authentification à deux facteurs, renforçant la sécurité en exigeant une seconde vérification (comme une application mobile ou un token).  
    * Gestion des sessions et des jetons : Keycloak permet de configurer la durée de vie des sessions et des jetons, et peut forcer la révocation de jetons en cas de compromission.  
    * Audit et Logs : Keycloak offre des capacités de logging pour suivre les connexions, les échecs d'authentification, et les autres événements de sécurité, ce qui est essentiel pour les exigences de conformité

      Paramétrages 

    * Au déploiement de la stack, Phrasea joue un  configurateur paramétrant un royaume et les clients dédiés à cette installation. Ces clients sont exploités par les différents services de Phrasea.   
    * Un compte root keycloack et ainsi qu’un compte master admin de l’application sont aussi initialisés.  
    * la déclaration des interconnections avec l’idp du client est faite manuellement dans un second temps  
    * Phrasea peut être configuré avec OAuth2 ou OpenID Connect pour l’authentification des utilisateurs, permettant de centraliser la gestion des identités et de faciliter l’intégration avec des fournisseurs tiers.

      Chiffrement 

    * Le chiffrement des données en transit est assuré par TLS/SSL pour sécuriser les communications entre les utilisateurs et l'application. 

      

2. # Schéma d’Architecture  de Phrasea.

![Technical Information](technical-information.svg)

# 3 Pré-requis capacitaire d’infrastructure

Les pré requis capacitaires sont des données minimales à adapter en fonction de la destination de la plateforme, ils sont données à titre indicatif et nécessite une adaptation au context d’utilisation finale.

les metrics a prendre en compte sont  
 

- Service Phrasea mis en œuvre.  
- Nombre d'utilisateurs simultanés en écriture.   
- Nombre d'utilisateurs simultanés en lecture.  
  - Volumétrie de stockage pour les documents.   
    - Volumétrie des index de document.  
      - Nombre de renditions à générer.  
      - Nombre de versions attendues par document.    
- Nombre de traitements parallélisés mis en place.  
- SLA désiré.  
- Durée de rétention des logs.

  ## Développement et Test.

  - CPU:  6 CPU 
  - Ram: 16 Go
  - Capacité Disque pour la gestion de image docker: 100 Go
  - Capacité Disque des volumes temporaire: 100 Go

## Production

### Images Phrasea déployé avec Docker Compose et ou Helm 

 - CPU: 6 Cpu  
 - Ram: 16 Go  
 - Capacité Disque pour la gestion de image docker: 100 Go  
 - Capacité Disque des volumes temporaire: 100 Go

### Service managé

- Postgresql:

  - CPU: 2 CPU
  - Ram: 8 Go
  - Disque: 50 Go


- Object Storage:

	- non applicable

# 4 Orchestration du déploiement

## Prérequis docker compose

  - Docker version: 28.5.2  
  - Docker compose: 2.40.3  
 

## Prérequis Kubernetes helm

  - Kubernetes version : 1.29  
  - Helm Version: v3.17.1  
