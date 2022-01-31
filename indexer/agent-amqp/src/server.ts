import express, {Request} from 'express';
import {getEnvStrict} from "./env";
import {Response} from 'express';

const app = express();

app.use(express.json());

type ServerHandler = (path: string, res: Response, query: Record<string, string>) => void;

const serverHandlers: Record<string, ServerHandler> = {};

export function declareAssetServer(name: string, handler: ServerHandler) {
    serverHandlers[name] = handler;
}

app.get('/assets', async (req: Request<any, any, any, {
    path: string;
    source: string;
    [key: string]: string;
}>, res) => {
    const {path, source, ...rest} = req.query;

    if (!path) {
        res.status(400);
        res.send({
            error: 'Bad Request',
            error_description: `Missing "path" parameter`,
        });

        return;
    }

    try {
        serverHandlers[source](path, res, rest);
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
