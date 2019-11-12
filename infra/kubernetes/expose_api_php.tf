resource "kubernetes_service" "phraseanet-service-expose-api_php" {
  metadata {
    name = "expose-api-php"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "expose-api-php"
    }

    port {
      port        = 9000
      target_port = 9000
    }
  }
}

resource "kubernetes_deployment" "phraseanet-service-expose-api_php" {
  metadata {
    name = "expose-api-php"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "expose-api-php"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "expose-api-php"
        }
      }

      spec {
        volume {
          name = "phraseanet-service-config"

          secret {
            secret_name = "phraseanet-service-config"
          }
        }

        container {
          image             = "${var.REGISTRY_NAMESPACE}expose-api-php:${var.DOCKER_TAG}"
          name              = "expose-api-php"
          image_pull_policy = "Always"

          volume_mount {
            name       = "phraseanet-service-config"
            mount_path = "/configs"
          }

          env = [
            {
              name  = "APP_ENV"
              value = "${var.APP_ENV}"
            },
            {
              name  = "DB_USER"
              value = "${var.POSTGRES_USER}"
            },
            {
              name  = "DB_PASSWORD"
              value = "${var.POSTGRES_PASSWORD}"
            },
            {
              name  = "RABBITMQ_USER"
              value = "${var.RABBITMQ_DEFAULT_USER}"
            },
            {
              name  = "RABBITMQ_PASSWORD"
              value = "${var.RABBITMQ_DEFAULT_PASS}"
            },
            {
              name  = "AUTH_BASE_URL"
              value = "${var.AUTH_BASE_URL}"
            },
            {
              name  = "AUTH_CLIENT_ID"
              value = "${var.ADMIN_CLIENT_ID}"
            },
            {
              name  = "AUTH_CLIENT_RANDOM_ID"
              value = "${var.ADMIN_CLIENT_RANDOM_ID}"
            },
            {
              name  = "AUTH_CLIENT_SECRET"
              value = "${var.ADMIN_CLIENT_SECRET}"
            },
            {
              name  = "STORAGE_BUCKET_NAME="
              value = "${var.EXPOSE_STORAGE_BUCKET_NAME}"
            },
            {
              name  = "S3_STORAGE_ACCESS_KEY"
              value = "${var.S3_STORAGE_ACCESS_KEY}"
            },
            {
              name  = "S3_STORAGE_SECRET_KEY"
              value = "${var.S3_STORAGE_SECRET_KEY}"
            },
          ]
        }
      }
    }
  }
}
