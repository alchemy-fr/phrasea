resource "kubernetes_deployment" "notify-worker" {
  metadata {
    name = "notify-worker"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "notify-worker"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "notify-worker"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}notify-worker:${var.DOCKER_TAG}"
          name              = "notify-worker"
          image_pull_policy = "Always"

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
          ]
        }
      }
    }
  }
}
