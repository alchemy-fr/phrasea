resource "kubernetes_service" "uploader-api-nginx" {
  metadata {
    name = "uploader-api-nginx"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "uploader-api-nginx"
    }

    port {
      port        = 80
      target_port = 80
    }
  }
}

resource "kubernetes_deployment" "uploader-api-nginx" {
  metadata {
    name = "uploader-api-nginx"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "uploader-api-nginx"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "uploader-api-nginx"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}uploader-api-nginx:${ var.DOCKER_TAG }"
          name              = "uploader-api-nginx"
          image_pull_policy = "Always"
        }
      }
    }
  }
}
