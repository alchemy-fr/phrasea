/*
 * Injects the runtime configuration of a client into its index.html.
 *
 * Usage: qjs generate-env.mjs <dir>   (run by quickjs-ng, from the client root)
 *
 * - `config-compiler.js` in the current directory evaluates to a function
 *   `(stackConfig, env) => config`. Without that file, nothing is done.
 * - `stackConfig` is the JSON read from `STACK_CONFIG_SRC`
 *   (default: /etc/app/stack-config.json, written by get-config.sh), `{}` when
 *   the file does not exist; `env` holds the environment variables.
 * - `config.customHTML` is removed from the exposed configuration: each of its
 *   keys is a placeholder of the template replaced by the associated HTML.
 * - `<dir>/index.tpl.html` (copied from `<dir>/index.html` when missing) is
 *   written to `<dir>/index.html`, with `__TPL_CONFIG__` replaced by a
 *   `<script>window.config=…;</script>` element carrying the nonce given by
 *   `CONFIG_NONCE`, if any.
 *
 * Any failure prints a "generate-env error" on stderr and exits with code 1.
 */
import * as std from 'qjs:std';
import * as os from 'qjs:os';

const DEFAULT_STACK_CONFIG_SRC = '/etc/app/stack-config.json';

const colors = {red: 31, green: 32, yellow: 33, cyan: 36};

function styleText(color, text, fd) {
    if (!os.isatty(fd)) {
        return text;
    }

    return `\x1b[${colors[color]}m${text}\x1b[39m`;
}

function print(text) {
    std.out.puts(`${text}\n`);
}

function warn(text) {
    std.err.puts(`${text}\n`);
}

function error(err, stack) {
    std.err.puts(`${styleText('red', `generate-env error: ${err}`, 2)}\n`);
    if (stack) {
        std.err.puts(`${stack}\n`);
    }
    std.err.flush();
    std.exit(1);
}

function resolvePath(base, p) {
    const segments = [];
    const parts = (p.startsWith('/') ? p : `${base}/${p}`).split('/');
    parts.forEach(part => {
        if (part === '..') {
            segments.pop();
        } else if (part && part !== '.') {
            segments.push(part);
        }
    });

    return `/${segments.join('/')}`;
}

function fileExists(path) {
    return os.stat(path)[1] === 0;
}

function readFile(path) {
    const content = std.loadFile(path);
    if (content === null) {
        error(`Cannot read file "${path}"`);
    }

    return content;
}

function writeFile(path, content) {
    const f = std.open(path, 'w');
    if (!f) {
        error(`Cannot write file "${path}"`);
    }
    f.puts(content);
    f.close();
}

// Makes the JSON safe to inline in a <script> element: "</script>" or "<!--" in a value
// would otherwise break out of the script context.
function toInlineScriptJson(value) {
    return JSON.stringify(value)
        .replace(/</g, '\\u003c')
        .replace(/>/g, '\\u003e')
        .replace(/&/g, '\\u0026');
}

function main() {
    const dirArg = scriptArgs.slice(1).find(a => !a.startsWith('-'));
    if (!dirArg) {
        error('Missing directory argument');
    }

    const [cwd, cwdErr] = os.getcwd();
    if (cwdErr !== 0) {
        error(`Cannot get the current directory: ${std.strerror(cwdErr)}`);
    }

    const dirPath = resolvePath(cwd, dirArg);
    const [dir, dirErr] = os.realpath(dirPath);
    if (dirErr !== 0) {
        error(`Directory "${dirPath}" does not exist`);
    }

    const compilerSrc = `${cwd}/config-compiler.js`;
    if (!fileExists(compilerSrc)) {
        warn(
            styleText(
                'yellow',
                `No config compiler file found. (Looked at ${compilerSrc})`,
                2
            )
        );
        return;
    }

    const compiler = (0, eval)(readFile(compilerSrc));
    if (typeof compiler !== 'function') {
        error(`"${compilerSrc}" does not evaluate to a function`);
    }
    print(styleText('cyan', `Loading compiler at "${compilerSrc}"`, 1));

    const env = std.getenviron();
    const stackConfigSrc = env.STACK_CONFIG_SRC || DEFAULT_STACK_CONFIG_SRC;
    let stackConfig = {};
    if (fileExists(stackConfigSrc)) {
        stackConfig = JSON.parse(readFile(stackConfigSrc));
    }

    const config = compiler(stackConfig, env);

    let customHTML = {};
    if (config.customHTML) {
        customHTML = {...config.customHTML};
        delete config.customHTML;
    }

    print(
        `${styleText(
            'yellow',
            `
############################################
# The following configuration will be      #
# exposed publicly by the client.          #
# Please review there is no sensible data: #
############################################
`,
            1
        )} ${JSON.stringify(config, null, 2)}`
    );

    const scriptNonce = env.CONFIG_NONCE;
    if (scriptNonce && !/^[A-Za-z0-9+/_=-]+$/.test(scriptNonce)) {
        error('CONFIG_NONCE must be a base64 value');
    }
    const compiled = `<script${scriptNonce ? ` nonce="${scriptNonce}"` : ''}>window.config=${toInlineScriptJson(config)};</script>`;
    const indexSrc = `${dir}/index.html`;
    const tplSrc = `${dir}/index.tpl.html`;

    if (!fileExists(tplSrc)) {
        writeFile(tplSrc, readFile(indexSrc));
        print(styleText('green', `[OK] ${indexSrc} copied into ${tplSrc}`, 1));
    }

    // Replacements are passed as functions so that "$&", "$'"... in values are not
    // interpreted as replacement patterns.
    let html = readFile(tplSrc).replace('__TPL_CONFIG__', () => compiled);
    Object.keys(customHTML).forEach(k => {
        html = html.replace(k, () => customHTML[k]);
    });

    writeFile(indexSrc, html);
    print(styleText('green', `[OK] ${indexSrc} written`, 1));
}

try {
    main();
} catch (e) {
    error(e?.message ?? e, e?.stack);
}
