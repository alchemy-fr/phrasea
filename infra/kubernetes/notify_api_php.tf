resource "kubernetes_service" "notify_api_php" {
  metadata {
    name = "notify-api-php"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "notify_api_php"
    }

    port {
      port        = 9000
      target_port = 9000
    }
  }
}

resource "kubernetes_deployment" "notify_api_php" {
  metadata {
    name = "notify-api-php"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet"
        tier = "notify_api_php"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet"
          tier = "notify_api_php"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}notify_api_php:${var.DOCKER_TAG}"
          name              = "notify-api-php"
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
              value = "${var.ADMIN_CLIENT_RANDOM_ID}}"
            },
            {
              name  = "AUTH_CLIENT_SECRET"
              value = "${var.ADMIN_CLIENT_SECRET}"
            },
          ]
        }

        volume {
          name = "phraseanet-service-config"

          secret {
            secret_name = "phraseanet-service-config"
          }
        }
      }
    }
  }
}
