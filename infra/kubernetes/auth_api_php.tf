resource "kubernetes_service" "auth_api_php" {
  metadata {
    name = "auth-api-php"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "auth_api_php"
    }

    port {
      port        = 9000
      target_port = 9000
    }
  }
}

resource "kubernetes_deployment" "auth_api_php" {
  metadata {
    name = "auth-api-php"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "auth_api_php"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "auth_api_php"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}auth_api_php:${var.DOCKER_TAG}"
          name              = "auth-api-php"
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
              name  = "REGISTRATION_VALIDATE_EMAIL"
              value = "${var.AUTH_REGISTRATION_VALIDATE_EMAIL}"
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
              name  = "MAILER_URL"
              value = "${var.MAILER_URL}"
            },
            {
              name  = "RABBITMQ_USER"
              value = "${var.RABBITMQ_DEFAULT_USER}"
            },
            {
              name  = "RABBITMQ_PASSWORD"
              value = "${var.RABBITMQ_DEFAULT_PASS}"
            },
          ]
        }

        volume {
          name = "phraseanet-service-config"

          persistent_volume_claim {
            claim_name = "phraseanet-service-config"
          }
        }
      }
    }
  }
}
