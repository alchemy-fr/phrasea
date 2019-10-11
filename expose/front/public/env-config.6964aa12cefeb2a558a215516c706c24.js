window.config = {
  "_env_": {
    "EXPOSE_BASE_URL": "http://localhost:8050",
    "AUTH_BASE_URL": "http://auth.alchemy.local",
    "DEV_MODE": "false",
    "CLIENT_ID": "expose-app_12356789abcdefghijklmnopqrstuvwx",
    "CLIENT_SECRET": "cli3nt_s3cr3t"
  },
  "available_locales": [
    "en",
    "fr",
    "es",
    "fr_CA"
  ],
  "auth": {
    "oauth_providers": [
      {
        "title": "Phraseanet",
        "name": "phraseanet",
        "type": "phraseanet",
        "options": {
          "client_id": "phraseanet",
          "client_secret": "xxx",
          "base_url": "https://elastic.preprod.alchemyasp.com"
        }
      }
    ]
  },
  "uploader": {
    "max_upload_file_size": 25165824,
    "max_upload_commit_size": 419430400,
    "max_upload_file_count": 2,
    "client": {
      "logo": {
        "src": "https://www.phraseanet.com/wp-content/uploads/2014/05/PICTO_PHRASEANET.png",
        "margin": "2px 10px"
      }
    },
    "admin": {
      "logo": {
        "src": "https://www.phraseanet.com/wp-content/uploads/2014/05/PICTO_PHRASEANET.png",
        "with": "80px"
      }
    }
  },
  "notifier": {
    "admin": {
      "logo": {
        "src": "https://www.phraseanet.com/wp-content/uploads/2014/05/PICTO_PHRASEANET.png",
        "with": "80px"
      }
    }
  }
}
;
