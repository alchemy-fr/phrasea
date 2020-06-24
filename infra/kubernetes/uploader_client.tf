resource "kubernetes_service" "uploader_client" {
  metadata {
    name = "uploader-client"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "uploader_client"
    }

    port {
      port        = 80
      target_port = 80
    }
  }
}

resource "kubernetes_deployment" "uploader_client" {
  metadata {
    name = "uploader-client"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "uploader_client"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "uploader_client"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}uploader-client:${var.DOCKER_TAG}"
          name              = "uploader-client"
          image_pull_policy = "Always"

          volume_mount {
            name       = "phraseanet-service-config"
            mount_path = "/configs"
          }

          env = [
            {
              name  = "DEV_MODE"
              value = "${var.DEV_MODE}"
            },
            {
              name  = "UPLOADER_API_BASE_URL"
              value = "${var.UPLOADER_API_BASE_URL}"
            },
            {
              name  = "AUTH_BASE_URL"
              value = "${var.AUTH_BASE_URL}"
            },
            {
              name  = "CLIENT_ID"
              value = "${var.UPLOADER_CLIENT_ID}_${var.UPLOADER_CLIENT_RANDOM_ID}"
            },
            {
              name  = "CLIENT_SECRET"
              value = "${var.UPLOADER_CLIENT_SECRET}"
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
