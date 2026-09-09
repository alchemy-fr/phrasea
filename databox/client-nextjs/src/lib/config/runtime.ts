import type {AppConfig} from './types';

let current: AppConfig | null = null;

export function setRuntimeConfig(config: AppConfig): void {
    current = config;
}

export function getRuntimeConfig(): AppConfig {
    if (!current) {
        throw new Error('Runtime configuration is not available yet');
    }

    return current;
}
