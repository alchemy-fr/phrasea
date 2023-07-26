
declare global {
    interface Window {
        config: Record<string, any>;
    }
}

const configData = window.config;

class Config {
    get(key: string): any {
        return configData[key];
    }

    set(key: string, value: any): void {
        configData[key] = value;
    }

    getApiBaseUrl(): string {
        return configData.baseUrl;
    }

    getAuthBaseUrl(): string {
        return configData.authBaseUrl;
    }

    getClientId(): string {
        return this.get('clientId');
    }
}

const config = new Config();

export default config;
