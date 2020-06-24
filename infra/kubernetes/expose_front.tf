resource "kubernetes_service" "expose_client" {
  metadata {
    name = "expose-client"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "expose_client"
    }

    port {
      port        = 80
      target_port = 80
    }
  }
}

resource "kubernetes_deployment" "expose_client" {
  metadata {
    name = "phraseanet-service-expose-client"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "expose_client"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "expose_client"
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
          image             = "${var.REGISTRY_NAMESPACE}expose-client:${var.DOCKER_TAG}"
          name              = "expose-client"
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
              name  = "EXPOSE_API_BASE_URL"
              value = "${var.EXPOSE_API_BASE_URL}"
            },
            {
              name  = "AUTH_BASE_URL"
              value = "${var.AUTH_BASE_URL}"
            },
            {
              name  = "CLIENT_ID"
              value = "${var.EXPOSE_CLIENT_ID}_${var.EXPOSE_CLIENT_RANDOM_ID}"
            },
            {
              name  = "CLIENT_SECRET"
              value = "${var.EXPOSE_CLIENT_SECRET}"
            },
          ]
        }
      }
    }
  }
}
