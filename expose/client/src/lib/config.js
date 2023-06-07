const configData = window.config;

class Config {
    get(key) {
        return configData[key];
    }

    set(key, value) {
        configData[key] = value;
    }

    getApiBaseUrl() {
        return configData.baseUrl;
    }

    getAuthBaseUrl() {
        return configData.authBaseUrl;
    }

    getMatomoUrl() {
        return configData.matomoBaseUrl;
    }

    getMatomoSiteId() {
        return configData.matomoSiteId;
    }

    getClientCredential() {
        return {
            clientId: this.get('clientId'),
            clientSecret: this.get('clientSecret'),
        };
    }
}

const config = new Config();

export default config;
