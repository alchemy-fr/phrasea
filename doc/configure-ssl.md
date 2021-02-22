# Local development with SSL

Use [nginx-ssl.conf](../infra/dev/nginx-ssl.conf) file in NGINX.
Ensure the server listen to port 443.

Before reloading NGINX configuration, you need to generate your self-signed certificates. 

```bash
sudo ln -s `pwd`/infra/dev/nginx-ssl.conf /etc/nginx/sites-enabled/phraseanet-services-ssl.conf
./infra/ssl/create-root-ca.sh # You must define a passphrase
./infra/ssl/create-self-signed-certificate.sh
sudo service nginx configtest
sudo service nginx reload
```

Then you can import the AlchemyRootCA (located at `~/ssl/AlchemyRootCA.pem`) into "Authorities" in your favorite browser.
