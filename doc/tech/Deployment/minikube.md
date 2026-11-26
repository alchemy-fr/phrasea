# Deploy stack to minikube

The [Helm charts repository](https://github.com/alchemy-fr/phrasea-helm-charts) provides
`bin/minikube-test-deploy.sh`, which deploys the whole stack on a local minikube cluster (images
built from this repository by default) and checks the deployment: see its README.

To test a fresh image without redeploying, build it with the minikube Docker daemon, then restart
its pods:

```bash
eval $(minikube -p phrasea docker-env)
REGISTRY_NAMESPACE=alchemyfr/ps- DOCKER_TAG=minikube COMPOSE_FILE=docker-compose.yml docker compose build databox-api-php
kubectl --context phrasea -n phrasea rollout restart deploy/databox-api-php
```

Alternatively you can run a registry in minikube and push your images:
https://minikube.sigs.k8s.io/docs/handbook/pushing/#4-pushing-to-an-in-cluster-using-registry-addon
