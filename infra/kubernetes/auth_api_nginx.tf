resource "kubernetes_service" "auth-api-nginx" {
  metadata {
    name = "auth-api-nginx"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "auth-api-nginx"
    }

    port {
      port        = 80
      target_port = 80
    }
  }
}

resource "kubernetes_deployment" "auth-api-nginx" {
  metadata {
    name = "auth-api-nginx"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "auth-api-nginx"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "auth-api-nginx"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}auth-api-nginx:${ var.DOCKER_TAG }"
          name              = "auth-api-nginx"
          image_pull_policy = "Always"
        }
      }
    }
  }
}
