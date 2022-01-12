import express from 'express';
import {getEnvStrict} from "./env";
import {signUri} from "./s3";
import {SourceName} from "./sources";

const app = express();

app.use(express.json());

app.get('/assets', async (req, res) => {
    const path = req.body.path;
    const source = req.body.source as SourceName;

    res.redirect(await signUri(source, path), 307);
})

const port = getEnvStrict('SERVER_PORT');
app.listen(port, () => {
    console.log(`Indexer asset exposition server is listening at http://localhost:${port}`)
})
