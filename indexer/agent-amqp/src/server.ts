import express, {Request} from 'express';
import {getEnvStrict} from "./env";
import {Response} from 'express';
import {Logger} from "winston";

const app = express();

app.use(express.json());

type ServerHandler = (path: string, res: Response, query: Record<string, string>) => void;

const serverHandlers: Record<string, ServerHandler> = {};

export function declareAssetServer(name: string, handler: ServerHandler) {
    serverHandlers[name] = handler;
}

export function runServer(logger: Logger): void {
    app.get('/assets', async (req: Request<any, any, any, {
        path: string;
        source: string;
        [key: string]: string;
    }>, res) => {
        const {path, source, ...rest} = req.query;

        logger.debug(`GET /assets`, {
            path,
            source,
        });

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

            logger.error('GET /assets error', e);
        }
    })

    const port = getEnvStrict('SERVER_PORT');
    app.listen(port, () => {
        logger.info(`Server: listening at http://localhost:${port}`)
    });
}
