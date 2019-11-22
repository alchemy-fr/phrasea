resource "kubernetes_deployment" "uploader_worker" {
  metadata {
    name = "uploader-worker"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "uploader_worker"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "uploader_worker"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}uploader-worker:${var.DOCKER_TAG}"
          name              = "uploader-worker"
          image_pull_policy = "Always"

          volume_mount {
            name       = "upload-temp"
            mount_path = "/var/data/upload"
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
          ]
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
