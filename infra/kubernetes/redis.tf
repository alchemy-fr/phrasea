resource "kubernetes_service" "redis" {
  metadata {
    name = "redis"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "redis"
    }

    port {
      port        = 6380
      target_port = 6380
    }
  }
}

resource "kubernetes_deployment" "redis" {
  metadata {
    name = "phraseanet-service-redis"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "redis"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "redis"
        }
      }

      spec {
        container {
          image             = "redis:5.0.5-alpine"
          name              = "phraseanet-service-redis"
          image_pull_policy = "Always"

          volume_mount {
            name       = "phraseanet-service-redis"
            mount_path = "/data"
          }
        }
      }
    }
  }
}
