# User import

## CSV import

CSV file must contain columns header.
You must name your columns in order to match the target user.

Here are the supported column names:

- Email
- Admin | Administrateur
- Enabled | Active | Actif

> Column name are case insensitive

### Examples

```csv
Email,Admin,Enabled
enabled-account@foo.com,0,1
disabled-account@foo.com,0,0
admin-account@foo.com,1,1
```

This will work as well:
```csv
Enabled,Email,Admin,
1,enabled-account@foo.com,0
0,disabled-account@foo.com,0
1,admin-account@foo.com,1
```

This will generate an **error**:
```csv
Enabled,Email,Admin,
1,enabled-account@foo.com,0
0,invalidemail,0
0,disabled-account@foo.com,0
1,admin-account@foo.com,1
```

`Error at row #3: This value is not a valid email address.`
