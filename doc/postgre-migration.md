# Upgrade PostgreSQL server version

When upgrading to a newer version of PostgreSQL, you need to export your databases, then change server version, and import data back:
You can find helpers in `bin/ops/db`

Run this command **before** any docker-compose up:

```bash 
$ bin/ops/db/migrate-postgre.sh
```
