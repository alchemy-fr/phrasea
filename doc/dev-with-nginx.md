# NGINX setup

See [nginx.conf](nginx.conf)

You can directly link this file to your Nginx's configuration:

```bash
# cd /path/to/this/repo
sudo ln -s `pwd`/doc/nginx.conf /etc/nginx/sites-enabled/uploader.conf
sudo service nginx reload
```

Add the following entries to your `/etc/hosts` file:

```
127.0.0.1 uploader.local
127.0.0.1 api.uploader.local
127.0.0.1 auth.uploader.local
127.0.0.1 pgadmin.uploader.local
127.0.0.1 rabbit.uploader.local
```
