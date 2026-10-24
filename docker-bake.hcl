# Images built by the CI (.github/workflows/ci.yaml) with `docker buildx bake`.
#
# Each group comes with the group of its base images (infra/docker/*), to build
# and push first: the application Dockerfiles start
# FROM ${REGISTRY_NAMESPACE}<base>:${BASE_TAG}, resolved from the registry
# (their layers are already in the builder, nothing is downloaded again).
# Passing the bases as `target:` named contexts instead would rebuild every
# image on each run: the first step reading the local context of an inlined
# base gets a new cache key in every BuildKit session.
#
#   docker buildx bake -f docker-bake.hcl --print        # resolved configuration
#
# The defaults tag the images localhost:5000/ps-<name>:dev, so that a local build
# neither starts from nor overwrites the published images: the CI sets
# REGISTRY_NAMESPACE and DOCKER_TAG (see doc/tech/Development/ci.md).
#
# Without -f, bake also reads docker-compose.yml, whose services share some
# target names. docker-compose.yml keeps building the same Dockerfiles for
# development.

variable "REGISTRY_NAMESPACE" {
  default = "localhost:5000/ps-"
}

variable "DOCKER_TAG" {
  default = "dev"
}

variable "SENTRY_RELEASE" {
  default = "unknown"
}

# Version embedded in the API images (/_health/version, OpenAPI info.version):
# the tag name for a release, <branch>@<short sha> otherwise (ci.yaml).
variable "PHRASEA_VERSION" {
  default = "dev"
}

variable "PHRASEA_COMMIT" {
  default = ""
}

# Tag of the registry cache of each image ("build-cache-1" in the CI), next to
# the image itself. Empty: no registry cache.
variable "CACHE_TAG" {
  default = ""
}

function "image" {
  params = [name]
  result = "${REGISTRY_NAMESPACE}${name}:${DOCKER_TAG}"
}

function "cache_from" {
  params = [name]
  result = CACHE_TAG == "" ? [] : ["type=registry,ref=${REGISTRY_NAMESPACE}${name}:${CACHE_TAG}"]
}

function "cache_to" {
  params = [name]
  result = CACHE_TAG == "" ? [] : ["type=registry,mode=max,ref=${REGISTRY_NAMESPACE}${name}:${CACHE_TAG}"]
}

group "default" {
  targets = ["php", "node"]
}

group "php-base" {
  targets = ["php-fpm-base", "nginx-fpm-base", "nginx-cache-purge"]
}

group "php" {
  targets = ["configurator", "api"]
}

group "node-base" {
  targets = ["nodejs-base"]
}

group "node" {
  targets = ["client"]
}

target "_common" {
  attest = ["type=provenance,disabled=true"]
}

target "_app" {
  inherits = ["_common"]
  context  = "."
  args = {
    BASE_TAG           = DOCKER_TAG
    REGISTRY_NAMESPACE = REGISTRY_NAMESPACE
    SENTRY_RELEASE     = SENTRY_RELEASE
    PHRASEA_VERSION    = PHRASEA_VERSION
    PHRASEA_COMMIT     = PHRASEA_COMMIT
  }
}

target "base" {
  name       = base
  matrix     = { base = ["php-fpm-base", "nginx-fpm-base", "nodejs-base", "nginx-cache-purge"] }
  inherits   = ["_common"]
  context    = "infra/docker/${base}"
  tags       = [image(base)]
  cache-from = cache_from(base)
  cache-to   = cache_to(base)
}

target "configurator" {
  inherits   = ["_app"]
  dockerfile = "configurator/Dockerfile"
  tags       = [image("configurator")]
  cache-from = cache_from("configurator")
  cache-to   = cache_to("configurator")
}

target "api" {
  name       = "${app}-${stage}"
  matrix     = { app = ["databox", "expose", "uploader"], stage = ["api-php", "api-nginx", "worker"] }
  inherits   = ["_app"]
  dockerfile = "${app}/api/Dockerfile"
  target     = stage
  tags       = [image("${app}-${stage}")]
  cache-from = cache_from("${app}-${stage}")
  cache-to   = cache_to("${app}-${stage}")
}

target "client" {
  name       = img
  matrix     = { img = ["dashboard", "databox-client", "expose-client", "uploader-client"] }
  inherits   = ["_app"]
  dockerfile = "${trimsuffix(img, "-client")}/client/Dockerfile"
  tags       = [image(img)]
  cache-from = cache_from(img)
  cache-to   = cache_to(img)
}
