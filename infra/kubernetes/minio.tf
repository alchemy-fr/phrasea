resource "kubernetes_service" "minio" {
  metadata {
    name = "minio"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "minio"
    }

    port {
      port        = 9000
      target_port = 9000
    }
  }
}

resource "kubernetes_deployment" "minio" {
  metadata {
    name = "minio"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "minio"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "minio"
        }
      }

      spec {
        container {
          image             = "minio/minio"
          name              = "minio"
          image_pull_policy = "Always"

          command = [
            "server",
            "/data",
          ]

          volume_mount {
            name       = "phraseanet-service-minio"
            mount_path = "/data"
          }

          env = [
            {
              name  = "MINIO_ACCESS_KEY"
              value = "${var.S3_STORAGE_ACCESS_KEY}"
            },
            {
              name  = "MINIO_SECRET_KEY"
              value = "${var.S3_STORAGE_SECRET_KEY}"
            },
          ]
        }
      }
    }
  }
}
