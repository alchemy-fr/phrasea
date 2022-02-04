import express, {Request} from 'express';
import {getEnvStrict} from "./env";
import {Response} from 'express';
import {Logger} from "winston";
import {ConfigOptions, IndexLocation} from "./types/config";
import {assetServerFactories} from "./serverFactories";
import {getLocation} from "./locations";
import {createLogger} from "./lib/logger";

const app = express();

app.use(express.json());

export type AssetServerHandler = (path: string, res: Response, query: Record<string, string>) => void;
export type AssetServerFactory<T extends ConfigOptions> = (location: IndexLocation<T>, logger: Logger) => AssetServerHandler;

const servers: Record<string, AssetServerHandler> = {};

function getOrCreateServer(location: IndexLocation<any>): AssetServerHandler {
    if (servers.hasOwnProperty(location.name)) {
        return servers[location.name];
    }

    return servers[location.name] = assetServerFactories[location.type](location, createLogger(location.name));
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
        if (!source) {
            return badRequest(res, `Missing "source" parameter`, logger);
        }
        if (!path) {
            return badRequest(res, `Missing "path" parameter`, logger);
        }

        try {
            getOrCreateServer(getLocation(source))(path, res, rest);
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

function badRequest(res: Response, message: string, logger: Logger): void {
    res.status(400);
    res.send({
        error: 'Bad Request',
        error_description: message,
    });
    logger.warn(`HTTP Bad Request: ${message}`);
}

export function notFound(res: Response, message: string, logger: Logger): void {
    res.status(404);
    res.send({
        error: 'Not Found',
        error_description: message,
    });

    logger.warn(`HTTP Not found: ${message}`);
}
