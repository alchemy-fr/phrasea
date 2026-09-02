/**
 * SSR server for the databox client.
 *
 * Only the public share page (/s/:id/:token) is server-rendered;
 * every other route is served as the classic SPA shell.
 *
 * Dev:  node server.mjs           (vite in middleware mode, HMR included)
 * Prod: NODE_ENV=production node server.mjs
 *       (requires `pnpm build:ssr` artifacts in dist/client + dist/server)
 */
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import {fileURLToPath} from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const port = parseInt(process.env.SSR_PORT ?? '3000', 10);

function loadTemplate() {
    const templatePath = isProduction
        ? path.resolve(__dirname, 'dist/client/index.html')
        : path.resolve(__dirname, 'index.html');

    return fs.readFileSync(templatePath, 'utf-8');
}

function parseWindowConfig(template) {
    const m = template.match(/window\.config\s*=\s*(\{.*?\});?\s*<\/script>/s);
    if (!m) {
        throw new Error('Unable to extract window.config from index.html');
    }

    return JSON.parse(m[1].replace(/;\s*$/, ''));
}

function setupGlobalShims(config) {
    // The client module graph (init.ts, i18n…) expects a browser-like
    // environment at import time. `document` is intentionally left
    // undefined so that emotion & js-cookie stay in server mode.
    const nav =
        globalThis.navigator && globalThis.navigator.languages
            ? globalThis.navigator
            : {languages: ['en'], language: 'en'};

    globalThis.window = {
        config,
        navigator: nav,
        location: {
            pathname: '/',
            search: '',
            hash: '',
            href: config.baseUrl ?? 'http://localhost/',
        },
        addEventListener() {},
        removeEventListener() {},
    };
}

function injectSsr(template, ssr) {
    let html = template;

    if (ssr.headTags.includes('<title>')) {
        html = html.replace(/<title>.*?<\/title>/s, '');
    }

    html = html.replace(
        '</head>',
        `    ${ssr.headTags}\n    ${ssr.styleTags}\n</head>`
    );

    const stateScript = `<script>window.__REACT_QUERY_STATE__=${JSON.stringify(
        ssr.dehydratedState
    ).replace(/</g, '\\u003c')};</script>`;

    html = html.replace(
        '<div id="root"></div>',
        `<div id="root">${ssr.html}</div>\n${stateScript}`
    );

    return html;
}

async function createServer() {
    const template = loadTemplate();
    const config = parseWindowConfig(template);

    if (process.env.SSR_API_BASE_URL) {
        // Server-side API calls may need an internal endpoint
        // (public hostname/TLS not always reachable from the container)
        config.baseUrl = process.env.SSR_API_BASE_URL;
    }

    setupGlobalShims(config);

    let vite;
    let render;
    let serveStatic;

    if (!isProduction) {
        const {createServer: createViteServer} = await import('vite');
        vite = await createViteServer({
            root: __dirname,
            server: {middlewareMode: true},
            appType: 'custom',
        });
        render = async url =>
            (await vite.ssrLoadModule('/src/entry-server.tsx')).render(url);
    } else {
        render = (await import('./dist/server/entry-server.js')).render;
        const {default: sirv} = await import('sirv');
        serveStatic = sirv(path.resolve(__dirname, 'dist/client'), {
            extensions: [],
        });
    }

    const server = http.createServer(async (req, res) => {
        const url = req.url ?? '/';

        try {
            const sendHtml = async ssr => {
                let html = isProduction
                    ? template
                    : await vite.transformIndexHtml(url, template);
                if (ssr) {
                    html = injectSsr(html, ssr);
                }
                res.statusCode = 200;
                res.setHeader('Content-Type', 'text/html; charset=utf-8');
                res.end(html);
            };

            const isPageRequest =
                req.method === 'GET' &&
                !url.includes('.') &&
                !url.startsWith('/@') &&
                !url.startsWith('/src/') &&
                !url.startsWith('/node_modules/');

            if (isPageRequest) {
                let ssr = null;
                try {
                    ssr = await render(url);
                } catch (e) {
                    vite?.ssrFixStacktrace(e);
                    console.error(
                        `SSR failed for ${url}, falling back to SPA:`,
                        e
                    );
                }

                await sendHtml(ssr);

                return;
            }

            if (!isProduction) {
                vite.middlewares(req, res, async () => {
                    await sendHtml(null);
                });
            } else {
                serveStatic(req, res, async () => {
                    await sendHtml(null);
                });
            }
        } catch (e) {
            vite?.ssrFixStacktrace(e);
            console.error(e);
            res.statusCode = 500;
            res.end('Internal Server Error');
        }
    });

    server.listen(port, '0.0.0.0', () => {
        console.log(
            `SSR server (${isProduction ? 'prod' : 'dev'}) listening on http://0.0.0.0:${port}`
        );
    });
}

createServer();
