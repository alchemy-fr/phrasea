# Deploy stack to minikube

## Setup

```bash
bin/dev/minikube-configure.sh
minikube start
minikube addons enable ingress
```

## Build local image in minikube

If you need to test your fresh image directly into minikube cluster, you need to build them
with the Minikube Docker daemon:

```bash
eval $(minikube docker-env)
docker-compose build
```

Alternatively you can run a registry in minikube and push your images:
https://minikube.sigs.k8s.io/docs/handbook/pushing/#4-pushing-to-an-in-cluster-using-registry-addon
