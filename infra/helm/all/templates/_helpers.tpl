{{- define "ps.fullname" -}}
{{- if .Values.fullnameOverride -}}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- $name := default "ps" .Values.nameOverride -}}
{{- if contains $name .Release.Name -}}
{{- .Release.Name | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" -}}
{{- end -}}
{{- end -}}
{{- end -}}

{{- define "ps.name" -}}
{{- default "ps" .Values.nameOverride | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "volumes.configs" }}
- name: configs
  configMap:
    name: {{ .Values.globalConfig.externalConfigmapName | default (printf "%s-configs" .Release.Name) }}
{{- end }}

{{- define "secretRef.adminOAuthClient" }}
- secretRef:
    name: {{ .Values.params.adminOAuthClient.externalSecretName | default (printf "%s-admin-oauth-client-secret" .Release.Name) }}
{{- end }}

{{- define "secretRef.ingress.tls.wildcard" -}}
{{- with .Values.ingress.tls.wildcard }}
{{- if and .enabled .externalSecretName -}}
{{- .externalSecretName -}}
{{- else -}}
gateway-tls
{{- end }}
{{- end }}
{{- end }}

{{- define "secretRef.rabbitmq" }}
- secretRef:
    name: {{ .Values.rabbitmq.externalSecretName | default "api-rabbitmq-secret" }}
{{- end }}

{{- define "secretRef.postgresql" }}
- secretRef:
    name: {{ .Values.postgresql.externalSecretName | default "api-db-secret" }}
{{- end }}

{{- define "configMapRef.phpApp" }}
- configMapRef:
    name: php-config
- configMapRef:
    name: urls-config
{{- end }}

{{- define "app.volumes" }}
{{- $appName := .app -}}
{{- $ctx := .ctx -}}
{{- $glob := .glob -}}
{{- if hasKey .glob.Values._internal.volumes $appName }}
{{- with (index .glob.Values._internal.volumes $appName) }}
{{- range $key, $value := . }}
- name: {{ $key }}
{{- if $ctx.persistence.enabled }}
  persistentVolumeClaim:
    claimName: {{ $ctx.persistence.existingClaim | default (printf "%s-%s" $value.name (include "ps.fullname" $glob)) }}
{{- else }}
  emptyDir: {}
{{- end }}
{{- end }}
{{- end }}
{{- end }}
{{- end }}

{{- define "app.volumes_mounts" }}
{{- $appName := .app -}}
{{- $ctx := .ctx -}}
{{- $glob := .glob -}}
{{- if hasKey .glob.Values._internal.volumes $appName }}
{{- with (index .glob.Values._internal.volumes $appName) }}
{{- range $key, $value := . }}
- name: {{ $key }}
  mountPath: {{ $value.mountPath }}
{{- end }}
{{- end }}
{{- end }}
{{- end }}

{{- define "app.volumesUidInit" }}
{{- $appName := .app -}}
{{- $ctx := .ctx -}}
{{- $glob := .glob -}}
{{- if hasKey .glob.Values._internal.volumes $appName }}
{{- with (index .glob.Values._internal.volumes $appName) }}
{{- range $key, $value := . }}
{{- if $value.uid }}
initContainers:
- name: volume-set-uid-{{ $appName }}-{{ $key }}
  image: busybox
  command: ["sh", "-c", "chown -R {{ $value.uid }}:{{ $value.uid }} {{ $value.mountPath }}"]
  volumeMounts:
  - name: {{ $key }}
    mountPath: {{ $value.mountPath }}
{{- end }}
{{- end }}
{{- end }}
{{- end }}
{{- end }}
