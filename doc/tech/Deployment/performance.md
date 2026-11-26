---
title: API performance
---
# API performance: PHP-FPM pool and slow requests

## PHP-FPM pool

Each API container (`databox-api-php`, `expose-api-php`, `uploader-api-php`, `configurator`) serves its requests with a
PHP-FPM pool. A worker handles one request at a time: once every worker is busy, the next requests wait in the listen
queue of FPM, even the quick ones (listing the workspaces…), until a worker is free or the gateway or the client gives
up. Behind a Kubernetes ingress, the default `proxy-read-timeout` (60 s) answers 504; the databox client gives up after
60 s ("Request timed out").

The pool is set by environment variables of the PHP containers:

| Variable                       | Default   | FPM setting            |
|--------------------------------|-----------|------------------------|
| `PHP_FPM_PM`                   | `dynamic` | `pm`                   |
| `PHP_FPM_PM_MAX_CHILDREN`      | `12`      | `pm.max_children`      |
| `PHP_FPM_PM_START_SERVERS`     | `3`       | `pm.start_servers`     |
| `PHP_FPM_PM_MIN_SPARE_SERVERS` | `2`       | `pm.min_spare_servers` |
| `PHP_FPM_PM_MAX_SPARE_SERVERS` | `6`       | `pm.max_spare_servers` |
| `PHP_FPM_PM_MAX_REQUESTS`      | `500`     | `pm.max_requests`      |

With `dynamic`, `PHP_FPM_PM_MIN_SPARE_SERVERS` ≤ `PHP_FPM_PM_START_SERVERS` ≤ `PHP_FPM_PM_MAX_SPARE_SERVERS` ≤
`PHP_FPM_PM_MAX_CHILDREN`, or FPM refuses to start.

### Sizing

Every worker may use up to `PHP_MEMORY_LIMIT` (`132M` in the images), and the opcache (256M) is shared by all the
workers of a container:

```
PHP_FPM_PM_MAX_CHILDREN × PHP_MEMORY_LIMIT + 256M ≤ memory limit of the container
```

The defaults (12 × 132M + 256M ≈ 1.8G) fit a 2Gi container. For more concurrent requests, add replicas of the API
rather than workers beyond this limit: a container over its memory limit is killed with all its requests.

### Is the pool saturated?

- The logs of the PHP container show
  `WARNING: [pool www] server reached pm.max_children setting (12), consider raising it`.
- The status page of FPM, from inside the container:

  ```bash
  kubectl exec <databox-api-php pod> -- php-fpm-healthcheck -v
  # or, with Docker Compose
  docker compose exec databox-api-php php-fpm-healthcheck -v
  ```

  `listen queue` is the number of requests waiting for a worker right now, `max listen queue` the highest since
  FPM started, `max children reached` how many times every worker was busy, `active processes` the busy workers.

## Measuring where the time goes

### Server-Timing

Every API response has a `Server-Timing` header, shown by the browser devtools (Network > the request > Timing):

- `app`: the time PHP spent on the request, from the moment a worker got it.
- `es` (databox): the time spent in Elasticsearch requests, with their number.

### NGINX access log

The NGINX containers of databox and uploader log every request with its timings, after the usual `combined` fields:

- `rt`: the whole request, as seen by NGINX.
- `urt`: the response of PHP-FPM, time spent in the FPM listen queue included.
- `uct`: the connection to PHP-FPM.

An `urt` much longer than the `app` duration of the same request is time spent waiting for a free FPM worker: the pool
is saturated. An `app` duration close to `es` means Elasticsearch is the slow part.

### Slow request log

The APIs log a `Slow request` warning for every request longer than `SLOW_REQUEST_LOG_THRESHOLD_MS` (default `10000`,
`0` disables it), with its method, route, path (without the query string, which may hold search terms), status,
duration, the backend timings (`es`…) and the peak memory. It goes to the logs of the PHP container only,
not to Sentry.
