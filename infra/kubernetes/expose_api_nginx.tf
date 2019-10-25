resource "kubernetes_service" "expose_api_nginx" {
  metadata {
    name = "expose-api-nginx"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "expose_api_nginx"
    }

    port {
      port        = 80
      target_port = 80
    }
  }
}

resource "kubernetes_deployment" "expose_api_nginx" {
  metadata {
    name = "expose-api-nginx"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "expose_api_nginx"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "expose_api_nginx"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}expose_api_nginx:${ var.DOCKER_TAG }"
          name              = "expose-api-nginx"
          image_pull_policy = "Always"
        }
      }
    }
  }
}
