const configData = window.config;

class Config {
    get(key) {
        return configData[key];
    }

    getApiBaseUrl() {
        return configData.baseUrl;
    }

    getAuthBaseUrl() {
        return configData.authBaseUrl;
    }
}

const config = new Config();

export default config;
