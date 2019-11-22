resource "kubernetes_deployment" "phraseanet-service-auth_worker" {
  metadata {
    name = "phraseanet-service-auth-worker"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "auth_worker"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "auth_worker"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}auth-worker:${var.DOCKER_TAG}"
          name              = "auth-worker"
          image_pull_policy = "Always"

          volume_mount {
            name       = ""
            mount_path = ""
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
