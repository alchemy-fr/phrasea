resource "kubernetes_service" "notify_api_nginx" {
  metadata {
    name = "notify-api-nginx"
  }

  spec {
    selector {
      app  = "phraseanet-service"
      tier = "notify_api_nginx"
    }

    port {
      port        = 80
      target_port = 80
    }
  }
}

resource "kubernetes_deployment" "notify_api_nginx" {
  metadata {
    name = "notify-api-nginx"
  }

  spec {
    replicas = 1

    selector {
      match_labels {
        app  = "phraseanet-service"
        tier = "notify_api_nginx"
      }
    }

    template {
      metadata {
        labels {
          app  = "phraseanet-service"
          tier = "notify_api_nginx"
        }
      }

      spec {
        container {
          image             = "${var.REGISTRY_NAMESPACE}notify_api_nginx:${ var.DOCKER_TAG }"
          name              = "notify-api-nginx"
          image_pull_policy = "Always"
        }
      }
    }
  }
}
