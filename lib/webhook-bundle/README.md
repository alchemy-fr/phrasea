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

Add AdminControllerTrait to your AdminController

```php
use Alchemy\WebhookBundle\Controller\AdminControllerTrait;
use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController;

class AdminController extends EasyAdminController
{
    use AdminControllerTrait;
}
```
