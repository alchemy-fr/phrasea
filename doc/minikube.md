# Deploy stack to minikube

## Setup

```bash
minikube start
minikube addons enable ingress
```

Get the minikube IP:

```bash
minikube ip
```

Refer to [sample.yaml](../infra/helm/sample.yaml) to get all domains.
Add these domains to your `/etc/hosts`:

```
# Alchemy Minikube
192.168.49.2 api-auth.alchemy.kube
192.168.49.2 uploader.alchemy.kube
# ... add other domains
```

For a quicker setup we will use the nginx configuration explained in [dev-with-nginx](./dev-with-nginx.md)

## Build local image in minikube

If you need to test your fresh image directly into minikube cluster, you need to build them
with the Mminikube Docker daemon:

```bash
eval $(minikube docker-env)
docker-compose build
```

Alternatively you can run a registry in minikube and push your images:
https://minikube.sigs.k8s.io/docs/handbook/pushing/#4-pushing-to-an-in-cluster-using-registry-addon

# Usage

```bash
bin/dev/deploy-minikube.sh install
bin/dev/deploy-minikube.sh update
bin/dev/deploy-minikube.sh uninstall
```
