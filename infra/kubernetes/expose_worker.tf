resource "kubernetes_deployment" "expose_worker" {
  metadata {
    name = "expose-worker"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "expose_worker"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "expose_worker"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}expose-worker:${var.DOCKER_TAG}"
          name              = "expose-worker"
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
            {
              name  = "STORAGE_BUCKET_NAME"
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
