resource "kubernetes_service" "rabbitmq" {
  metadata {
    name = "rabbitmq"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "rabbitmq"
    }

    port {
      port        = 15672
      target_port = 15672
    }
  }
}

resource "kubernetes_deployment" "rabbitmq" {
  metadata {
    name = "phraseanet-service-rabbitmq"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "rabbitmq"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "rabbitmq"
        }
      }

      spec {
        container {
          image             = "rabbitmq:3.7.14-management"
          name              = "phraseanet-service-rabbitmq"
          image_pull_policy = "Always"

          env = [
            {
              name  = "RABBITMQ_DEFAULT_USER"
              value = "${var.RABBITMQ_DEFAULT_USER}"
            },
            {
              name  = "RABBITMQ_DEFAULT_PASS"
              value = "${var.RABBITMQ_DEFAULT_PASS}"
            },
          ]
        }
      }
    }
  }
}
