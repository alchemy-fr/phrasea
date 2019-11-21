# NGINX setup

See [nginx.conf](../infra/dev/nginx.conf)

You can directly link this file to your Nginx's configuration:

```bash
# cd /path/to/this/repo
sudo ln -s `pwd`/infra/dev/nginx.conf /etc/nginx/sites-enabled/phraseanet-services.conf
sudo service nginx reload
```

Add the following entries to your `/etc/hosts` file:

```
127.0.0.1 uploader.alchemy.local
127.0.0.1 api.uploader.alchemy.local
127.0.0.1 auth.alchemy.local
127.0.0.1 pgadmin.alchemy.local
127.0.0.1 rabbit.alchemy.local
127.0.0.1 expose.alchemy.local
127.0.0.1 api.expose.alchemy.local
127.0.0.1 minio.alchemy.local
127.0.0.1 matomo.alchemy.local
127.0.0.1 notify.alchemy.local
```
