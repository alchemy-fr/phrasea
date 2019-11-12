resource "kubernetes_service" "expose-api-nginx" {
  metadata {
    name = "expose-api-nginx"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "expose-api-nginx"
    }

    port {
      port        = 80
      target_port = 80
    }
  }
}

resource "kubernetes_deployment" "expose-api-nginx" {
  metadata {
    name = "expose-api-nginx"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "expose-api-nginx"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "expose-api-nginx"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}expose-api-nginx:${ var.DOCKER_TAG }"
          name              = "expose-api-nginx"
          image_pull_policy = "Always"
        }
      }
    }
  }
}
