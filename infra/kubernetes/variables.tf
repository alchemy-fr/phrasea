variable "REGISTRY_NAMESPACE" {
  description = "Docker registry base url"
}

variable "DOCKER_TAG" {
  description = "Docker tag"
}

variable "APP_ENV" {
  description = "Symfony env var"
  default     = "prod"
}

variable "DEV_MODE" {
  description = "Enables some features for debugging applications"
  default     = "false"
}

variable "AUTH_PORT" {
  description = ""
  default     = 8060
}

variable "AUTH_BASE_URL" {
  description = ""
  default     = "http://localhost:8060"
}

variable "AUTH_REGISTRATION_VALIDATE_EMAIL" {
  description = ""
  default     = "true"
}

variable "DEFAULT_USER_EMAIL" {
  description = ""
  default     = "admin@alchemy.fr"
}

variable "DEFAULT_USER_PASSWORD" {
  description = ""
  default     = "IFK1w3kTQ2Vfw8KdWpWD"
}

variable "UPLOADER_CLIENT_ID" {
  description = "OAuth client ID for Auth service"
  default     = "uploader-app"
}

variable "UPLOADER_CLIENT_RANDOM_ID" {
  description = "OAuth client secret for Auth service (left part)"
  default     = "12345"
}

variable "UPLOADER_CLIENT_SECRET" {
  description = "OAuth client secret for Auth service (right part)"
  default     = "cli3nt_s3cr3t"
}

variable "UPLOADER_CLIENT_PORT" {
  description = ""
  default     = 8040
}

variable "ASSET_CONSUMER_COMMIT_URI" {
  description = ""
  default     = "http://localhost:9999/api/v1/upload/enqueue/"
}

variable "ASSET_CONSUMER_ACCESS_TOKEN" {
  description = ""
  default     = "define-me"
}

variable "UPLOADER_API_PORT" {
  description = ""
  default     = 8080
}

variable "UPLOADER_API_BASE_URL" {
  description = ""
  default     = "http://localhost:8080"
}

variable "AUTH_ADMIN_CLIENT_ID" {
  description = ""
  default     = "auth-admin"
}

variable "AUTH_ADMIN_CLIENT_RANDOM_ID" {
  description = ""
  default     = "12345"
}

variable "AUTH_ADMIN_CLIENT_SECRET" {
  description = ""
  default     = "cli3nt_s3cr3t"
}

variable "EXPOSE_ADMIN_CLIENT_ID" {
  description = ""
  default     = "expose-admin"
}

variable "EXPOSE_ADMIN_CLIENT_RANDOM_ID" {
  description = ""
  default     = "12345"
}

variable "EXPOSE_ADMIN_CLIENT_SECRET" {
  description = ""
  default     = "cli3nt_s3cr3t"
}

variable "UPLOADER_ADMIN_CLIENT_ID" {
  description = ""
  default     = "uploader-admin"
}

variable "UPLOADER_ADMIN_CLIENT_RANDOM_ID" {
  description = ""
  default     = "12345"
}

variable "UPLOADER_ADMIN_CLIENT_SECRET" {
  description = ""
  default     = "cli3nt_s3cr3t"
}

variable "NOTIFY_ADMIN_CLIENT_ID" {
  description = ""
  default     = "notify-admin"
}

variable "NOTIFY_ADMIN_CLIENT_RANDOM_ID" {
  description = ""
  default     = "12345"
}

variable "NOTIFY_ADMIN_CLIENT_SECRET" {
  description = ""
  default     = "cli3nt_s3cr3t"
}

variable "EXPOSE_API_PORT" {
  description = ""
  default     = 8050
}

variable "EXPOSE_FRONT_PORT" {
  description = ""
  default     = 8051
}

variable "EXPOSE_API_BASE_URL" {
  description = ""
  default     = "http://localhost:8050"
}

variable "EXPOSE_CLIENT_ID" {
  description = "OAuth client ID for Auth service"
  default     = "expose-app"
}

variable "EXPOSE_CLIENT_RANDOM_ID" {
  description = "OAuth client secret for Auth service (left part)"
  default     = "12345"
}

variable "EXPOSE_CLIENT_SECRET" {
  description = "OAuth client secret for Auth service (right part)"
  default     = "cli3nt_s3cr3t"
}

variable "EXPOSE_STORAGE_ROOT_URL" {
  description = ""
  default     = "https://s3-expose.alchemy.local/expose"
}

variable "EXPOSE_STORAGE_BUCKET_NAME" {
  description = ""
  default     = "expose"
}

variable "NOTIFY_PORT" {
  description = ""
  default     = 8083
}

variable "RABBITMQ_MGT_PORT" {
  description = ""
  default     = 8082
}

variable "RABBITMQ_DEFAULT_USER" {
  description = ""
  default     = "alchemy"
}

variable "RABBITMQ_DEFAULT_PASS" {
  description = ""
  default     = "w2dc4c2QklvA23rVuZY2"
}

variable "PGADMIN_PORT" {
  description = ""
  default     = 5050
}

variable "POSTGRES_DB" {
  description = ""
  default     = "alchemy"
}

variable "POSTGRES_USER" {
  description = ""
  default     = "alchemy"
}

variable "POSTGRES_PASSWORD" {
  description = ""
  default     = "3IKYHEZZn0EQbOzeEQC1"
}

variable "PGADMIN_DEFAULT_EMAIL" {
  description = ""
  default     = "admin@alchemy.fr"
}

variable "PGADMIN_DEFAULT_PASSWORD" {
  description = ""
  default     = "CxkngkeTRPkJOyniPHmZ"
}

variable "MAILER_DSN" {
  description = ""
  default     = "smtp://mailhog:1025"
}

variable "MINIO_PORT" {
  description = ""
  default     = 8010
}

variable "S3_STORAGE_ACCESS_KEY" {
  description = ""
  default     = "yHmXqQcQkB7Jg4LDb7v4BfKXE5vTnslDvNyeWMlMmi"
}

variable "S3_STORAGE_SECRET_KEY" {
  description = ""
  default     = "xlhnTOxqG66SLTXSjtHpZFA0x96WHJztksXrKUHYUA"
}
