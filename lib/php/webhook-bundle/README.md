# Webhook bundle

# Setup

Add form theme to easy_admin:

```yaml
# config/packages/admin.yaml

easy_admin:
  design:
    form_theme:
      - '@EasyAdmin/form/bootstrap_4.html.twig'
      - '...'
      - '@AlchemyWebhookBundle/views/form.html.twig'
```
