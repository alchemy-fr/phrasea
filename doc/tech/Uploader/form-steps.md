# Form Steps

Uploader supports multi-step forms, allowing for dynamic and conditional form flows. This is useful when the information required from the user depends on previous answers.

## Step-by-Step Example

1. **Client requests the form schema:**

   The client initiates the process by requesting the initial form schema.
   ```bash
   curl -X POST -H "Content-Type: application/json" https://<UPLOAD_HOST>/schema -d '{}'
   ```
   > The JSON data is empty because no form data has been submitted yet.

2. **Client receives the form structure:**

   The server responds with the first step of the form schema.
   ```json
   {
     "type": "object",
     "required": ["databox"],
     "properties": {
       "databox": {
         "enum": {"foo": "Foo", "bar": "Bar"},
         "type": "string",
         "title": "Which data box destination?"
       }
     }
   }
   ```

3. **Client submits the first form data:**

   ```bash
   curl -X POST -H "Content-Type: application/json" https://<UPLOAD_HOST>/schema -d '{"databox": "bar"}'
   ```

4. **Server responds with the next form fields:**

   Based on the submitted data, the server provides the next set of fields.
   ```json
   {
     "type": "object",
     "required": ["title"],
     "properties": {
       "title": {"type": "string", "title": "Title:"},
       "description": {"type": "string", "title": "Description:"}
     }
   }
   ```

5. **Client submits all form data (including previous steps):**

   ```bash
   curl -X POST -H "Content-Type: application/json" https://<UPLOAD_HOST>/schema -d '{"databox": "bar", "title": "My Asset", "description": "A description"}'
   ```
