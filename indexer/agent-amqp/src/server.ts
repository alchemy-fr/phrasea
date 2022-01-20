import express, {Request} from 'express';
import {getEnvStrict} from "./env";
import {signUri} from "./s3";

const app = express();

app.use(express.json());

app.get('/assets', async (req: Request<any, any, any, {
    path: string;
}>, res) => {
    const path = decodeURIComponent(req.query.path);
    const source = 's3main';
    if (!path) {
        res.status(400);
        res.send({
            error: 'Bad Request',
            error_description: `Missing "path" parameter`,
        });

        return;
    }

    try {
        res.redirect(307, await signUri(source, path));
    } catch (e) {
        res.status(500);
        res.send({
            error: 'Server Error',
            error_description: `Error: ${e.toString()}`,
        });

        console.error('GET /assets error', e);
    }
})

const port = getEnvStrict('SERVER_PORT');
app.listen(port, () => {
    console.log(`Server: listening at http://localhost:${port}`)
})
