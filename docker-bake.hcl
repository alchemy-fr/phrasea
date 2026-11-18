# Images built by the CI (.github/workflows/ci.yaml) with `docker buildx bake`.
#
# The base images (infra/docker/*) are bake targets passed to the applications
# as named build contexts: they are built in the same BuildKit session as the
# images that use them, never pushed nor pulled back from the registry.
#
#   docker buildx bake -f docker-bake.hcl --print        # resolved configuration
#   docker buildx bake -f docker-bake.hcl php --load     # build the PHP images locally
#
# Without -f, bake also reads docker-compose.yml, whose services share some
# target names. docker-compose.yml keeps building the same Dockerfiles for
# development.

variable "REGISTRY_NAMESPACE" {
  default = "alchemyfr/ps-"
}

variable "DOCKER_TAG" {
  default = "latest"
}

variable "SENTRY_RELEASE" {
  default = "unknown"
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

# Every target reads the cache of all the images of its group: BuildKit solves
# a stage shared by several targets (a base image, api-php) once per session,
# and only one of these targets records it in its own cache.
function "cache_from" {
  params = [names]
  result = CACHE_TAG == "" ? [] : [for name in names : "type=registry,ref=${REGISTRY_NAMESPACE}${name}:${CACHE_TAG}"]
}

function "php_images" {
  params = []
  result = concat(
    ["php-fpm-base", "nginx-fpm-base", "nginx-cache-purge", "configurator"],
    [for app in ["databox", "expose", "uploader"] : ["${app}-api-php", "${app}-api-nginx", "${app}-worker"]]...
  )
}

function "node_images" {
  params = []
  result = ["nodejs-base", "dashboard", "databox-client", "expose-client", "uploader-client", "databox-indexer"]
}

function "cache_to" {
  params = [name]
  result = CACHE_TAG == "" ? [] : ["type=registry,mode=max,ref=${REGISTRY_NAMESPACE}${name}:${CACHE_TAG}"]
}

# The Dockerfiles start FROM ${REGISTRY_NAMESPACE}<base>:${BASE_TAG}: the
# named context replaces exactly that reference.
function "base_context" {
  params = [name]
  result = { "${REGISTRY_NAMESPACE}${name}:${DOCKER_TAG}" = "target:${name}" }
}

function "api_nginx_base" {
  params = [app]
  result = app == "expose" ? "nginx-cache-purge" : "nginx-fpm-base"
}

group "default" {
  targets = ["php", "node"]
}

group "php" {
  targets = ["configurator", "api"]
}

group "node" {
  targets = ["client", "databox-indexer"]
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
  }
}

target "base" {
  name       = base
  matrix     = { base = ["php-fpm-base", "nginx-fpm-base", "nodejs-base", "nginx-cache-purge"] }
  inherits   = ["_common"]
  context    = "infra/docker/${base}"
  cache-from = cache_from(base == "nodejs-base" ? node_images() : php_images())
  cache-to   = cache_to(base)
}

target "configurator" {
  inherits   = ["_app"]
  dockerfile = "configurator/Dockerfile"
  contexts   = base_context("php-fpm-base")
  tags       = [image("configurator")]
  cache-from = cache_from(php_images())
  cache-to   = cache_to("configurator")
}

target "api" {
  name       = "${app}-${stage}"
  matrix     = { app = ["databox", "expose", "uploader"], stage = ["api-php", "api-nginx", "worker"] }
  inherits   = ["_app"]
  dockerfile = "${app}/api/Dockerfile"
  target     = stage
  contexts   = merge(base_context("php-fpm-base"), base_context(api_nginx_base(app)))
  tags       = [image("${app}-${stage}")]
  cache-from = cache_from(php_images())
  cache-to   = cache_to("${app}-${stage}")
}

target "client" {
  name       = img
  matrix     = { img = ["dashboard", "databox-client", "expose-client", "uploader-client"] }
  inherits   = ["_app"]
  dockerfile = "${trimsuffix(img, "-client")}/client/Dockerfile"
  contexts   = base_context("nodejs-base")
  tags       = [image(img)]
  cache-from = cache_from(node_images())
  cache-to   = cache_to(img)
}

target "databox-indexer" {
  inherits   = ["_app"]
  dockerfile = "databox/indexer/Dockerfile"
  contexts   = base_context("nodejs-base")
  tags       = [image("databox-indexer")]
  cache-from = cache_from(node_images())
  cache-to   = cache_to("databox-indexer")
}
