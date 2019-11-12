resource "kubernetes_service" "db" {
  metadata {
    name = "postgres"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "db"
    }

    port {
      port        = 5432
      target_port = 5432
    }
  }
}

resource "kubernetes_deployment" "phraseanet-service-db" {
  metadata {
    name = "phraseanet-service-db"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "db"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "db"
        }
      }

      spec {
        container {
          image             = "postgres:11.2-alpine"
          name              = "phraseanet-service-db"
          image_pull_policy = "Always"

          volume_mount {
            name       = "phraseanet-service-database"
            mount_path = "/var/lib/postgresql/data"
          }

          env = [
            {
              name  = "POSTGRES_USER"
              value = "${ var.POSTGRES_USER }"
            },
            {
              name  = "POSTGRES_PASSWORD"
              value = "${ var.POSTGRES_PASSWORD }"
            },
            {
              name  = "POSTGRES_DB"
              value = "${ var.POSTGRES_DB }"
            },
          ]
        }
      }
    }
  }
}
