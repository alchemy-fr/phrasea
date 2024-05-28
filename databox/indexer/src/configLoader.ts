import {Config} from './types/config';
import {getEnv, getEnvStrict} from './env';
import * as process from 'process';
import * as fs from 'fs';

function loadConfig(): object {
    return JSON.parse(
        fs.readFileSync(`${process.cwd()}/config/${getEnvStrict('CONFIG_FILE')}`).toString()
    );
}

function replaceEnv(str: string): string | boolean | number | undefined {
    let transform: string | undefined;
    let hasEnv = false;
    let result: string | undefined = str.replace(
        /%env\(([^^)]+)\)%/g,
        (_match, varName: string) => {
            const s = varName;
            hasEnv = true;

            let transformer: string | undefined, name: string;

            if (s.indexOf(':') > 0) {
                [transformer, name] = s.split(':');
            } else {
                name = s;
            }
            const v = getEnv(name);

            switch (transformer) {
                case 'bool':
                    transform = 'bool';
                    break;
                case 'int':
                    transform = 'int';
                    break;
            }

            return v || '';
        }
    );

    if (hasEnv && !result) {
        result = undefined;
    }

    switch (transform) {
        default:
            return result;
        case 'bool':
            return castToBoolean(result);
        case 'int':
            return castToInt(result);
    }
}

function parseConfig(config: any): any {
    if (typeof config === 'string') {
        return replaceEnv(config);
    }
    if (typeof config === 'object') {
        if (Array.isArray(config)) {
            return config.map(parseConfig);
        } else {
            const sub: Record<string, any> = {};
            Object.keys(config).forEach((k: string) => {
                sub[k] = parseConfig(config[k]);
            });
            return sub;
        }
    }

    return config;
}

export const config: Config = parseConfig(loadConfig());

export function getConfig(
    configPath: string,
    defaultValue: any = undefined,
    root: object = config
): any {
    const parts = configPath.split('.');
    let p = root;

    for (let i = 0; i < parts.length; ++i) {
        const k = parts[i] as string;
        if (!Object.prototype.hasOwnProperty.call(p, k)) {
            return defaultValue;
        }
        // @ts-expect-error any
        p = p[parts[i]];
    }

    if (undefined === p) {
        return defaultValue;
    }

    return p;
}

export function getStrict(configPath: string, root: object = config): any {
    const v = getConfig(configPath, undefined, root);
    if (!v) {
        console.error(`Missing config "${configPath}"`);
        process.exit(1);
    }

    return v;
}

export function castToBoolean(
    value: string | boolean | null | undefined
): boolean {
    if (typeof value === 'boolean') {
        return value;
    }

    if (value) {
        return ['true', '1', 'y'].includes(value);
    }

    return false;
}

export function castToInt(
    value: string | number | null | undefined
): number | undefined {
    if (typeof value === 'number') {
        return value;
    }

    if (!value) {
        return;
    }

    const n = parseInt(value);

    if (!isNaN(n)) {
        return n;
    }
}
