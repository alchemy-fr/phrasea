# Form steps

Form can have multiple steps:

1. Client request the form schema:

```bash
curl -X POST -H "Content-Type: application/json" https://<UPLOAD_HOST>/schema -d'{}'
```

> Note that JSON data is empty because no form data has been submitted yet.

2. Client receive the form structure:

```json
{
  "type": "object",
  "required": [
    "databox"
  ],
  "properties": {
    "databox": {
      "enum": {
        "foo": "Foo",
        "bar": "Bar"
      },
      "type": "string",
      "title": "Which data box destination?"
    }
  }
}
```

3. Client sends the first form data:

```bash
curl -X POST -H "Content-Type: application/json" https://<UPLOAD_HOST>/schema -d'{
  "databox": "bar"
}'
```

4. Depending on the submitted data, the server can now server the next form fields:

```json
{
  "type": "object",
  "required": [
    "title"
  ],
  "properties": {
    "title": {
      "type": "string",
      "title": "Title:"
    },
    "description": {
      "type": "string",
      "title": "Description:"
    }
  }
}
```

5. The client submits all the form data, including the previous steps' ones:

```bash
curl -X POST -H "Content-Type: application/json" https://<UPLOAD_HOST>/schema -d'{
  "databox": "bar"
}'
```
