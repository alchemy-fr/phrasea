resource "kubernetes_service" "uploader_api_nginx" {
  metadata {
    name = "uploader-api-nginx"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "uploader_api_nginx"
    }

    port {
      port        = 80
      target_port = 80
    }
  }
}

resource "kubernetes_deployment" "uploader_api_nginx" {
  metadata {
    name = "uploader-api-nginx"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "uploader_api_nginx"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "uploader_api_nginx"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}uploader-api-nginx:${var.DOCKER_TAG}"
          name              = "uploader-api-nginx"
          image_pull_policy = "Always"
        }
      }
    }
  }
}
