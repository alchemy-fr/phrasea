resource "kubernetes_service" "uploader_api_php" {
  metadata {
    name = "uploader-api-php"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "uploader_api_php"
    }

    port {
      port        = 9000
      target_port = 9000
    }
  }
}

resource "kubernetes_deployment" "uploader_api_php" {
  metadata {
    name = "phraseanet-service-uploader-api-php"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "uploader_api_php"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "uploader_api_php"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}uploader_api_php:${var.DOCKER_TAG}"
          name              = "uploader-api-php"
          image_pull_policy = "Always"

          volume_mount {
            name       = "upload-temp"
            mount_path = "/var/data/upload"
          }

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
              name  = "ASSET_CONSUMER_COMMIT_URI"
              value = "${var.ASSET_CONSUMER_COMMIT_URI}"
            },
            {
              name  = "ASSET_CONSUMER_ACCESS_TOKEN"
              value = "${var.ASSET_CONSUMER_ACCESS_TOKEN}"
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
              name  = "UPLOADER_BASE_URL"
              value = "${var.UPLOADER_BASE_URL}"
            },
            {
              name  = "UPLOAD_TEMP_DIR"
              value = "/var/data/upload"
            },
            {
              name  = "AUTH_BASE_URL"
              value = "${var.AUTH_BASE_URL}"
            },
            {
              name  = "AUTH_CLIENT_ID"
              value = "${var.ADMIN_CLIENT_ID}}"
            },
            {
              name  = "AUTH_CLIENT_RANDOM_ID"
              value = "${var.ADMIN_CLIENT_RANDOM_ID}"
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

        volume {
          name = "upload-temp"

          persistent_volume_claim {
            claim_name = "phraseanet-service-upload-temp"
          }
        }
      }
    }
  }
}
