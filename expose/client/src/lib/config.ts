
export type AnalyticsConfig = {
    matomo?: {
        baseUrl: string;
        siteId: string;
    }
}

export type TConfig = {
    analytics?: AnalyticsConfig;
    baseUrl: string;
    authBaseUrl: string;
    clientId: string;
    clientSecret: string;
}

const configData = (globalThis as any as {
    config: TConfig;
}).config;

class Config {
    get(key: keyof TConfig) {
        return configData[key];
    }

    set(key: keyof TConfig, value: string): any {
        configData[key] = value;
    }

    getApiBaseUrl(): string {
        return configData.baseUrl;
    }

    getAuthBaseUrl(): string  {
        return configData.authBaseUrl;
    }

    getAnalytics(): AnalyticsConfig {
        return (configData.analytics ?? {}) as AnalyticsConfig;
    }

    getClientCredential() {
        return {
            clientId: configData.clientId,
            clientSecret: configData.clientSecret,
        };
    }
}

const config = new Config();

export default config;
