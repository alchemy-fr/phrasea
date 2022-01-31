import {Config} from "./types/config";
import {getEnv} from "./env";

const fs = require('fs');

function loadConfig(): object {
    return JSON.parse(fs.readFileSync(__dirname + '/../config/config.json').toString());
}

function replaceEnv(str: string): string | boolean | number {
    let transform;
    const result = str.replace(/%env\(([^^)]+)\)%/g, (match, varName: string) => {
        const s = varName;

        let transformer: string | undefined,
            name: string;

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
        }

        return v;
    });

    switch (transform) {
        default:
            return result;
        case 'bool':
            return castToBoolean(result);
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
            const sub = {};
            Object.keys(config).forEach(k => {
                sub[k] = parseConfig(config[k]);
            })
            return sub;
        }
    }

    return config;
}

export const config: Config = parseConfig(loadConfig());

export function getConfig(configPath: string, defaultValue: any = undefined, root: object = config): any {
    const parts = configPath.split('.');
    let p = root;

    for (let i = 0; i < parts.length; ++i) {
        const k = parts[i];
        if (!p.hasOwnProperty(k)) {
            return defaultValue;
        }
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

export function castToBoolean(value: string | boolean | null | undefined): boolean {
    if (typeof value === 'boolean') {
        return value;
    }

    if (value) {
        return [
            'true',
            '1',
            'y'
        ].includes(value);
    }

    return false;
}
