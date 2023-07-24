import store from './store';

declare global {
    interface Window {
        config: any;
    }
}

window.config = window.config || {};

const configData = window.config;

type ClientCredentials = {
    clientId: string,
    clientSecret: string
};

class Config {
    get(key: string): any {
        return store.get(key) || configData[key];
    }

    all(): Record<string, any> {
        return configData;
    }

    set(key: string, value: any): void {
        store.set(key, value);
    }

    isDirectLoginForm(): boolean {
        return this.get('directLoginForm') ? this.get('directLoginForm') === 'true' : false;
    }

    setDirectLoginForm(directLoginForm: boolean): void {
        return this.set('directLoginForm', directLoginForm ? 'true' : 'false');
    }

    getBaseURL(): string {
        return this.get('baseUrl');
    }

    getAuthBaseUrl(): string {
        return this.get('authBaseUrl');
    }

    getClientId(): string {
        return this.get('clientId');
    }

    setClientCredential({clientId, clientSecret}: ClientCredentials): void {
        this.set('clientId', clientId);
        this.set('clientSecret', clientSecret);
    }

    setUploadBaseURL(url: string): void {
        this.set('baseUrl', url);
    }

    setAuthBaseURL(url: string): void {
        this.set('authBaseUrl', url);
    }

    devModeEnabled(): boolean {
        return configData.devMode;
    }
}

const config = new Config();

export default config;
