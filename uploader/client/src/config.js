import store from './store';
import i18n from "./locales/i18n";

const configData = window.config;

class Config {
    get(key) {
        return store.get(key) || configData[key];
    }

    all() {
        return configData;
    }

    set(key, value) {
        store.set(key, value);
    }

    isDirectLoginForm() {
        return this.get('directLoginForm') ? this.get('directLoginForm') === 'true' : false;
    }

    getUploadBaseURL() {
        return this.get('baseUrl');
    }

    getAvailableLocales() {
        return configData.locales;
    }

    getAuthBaseUrl() {
        return this.get('authBaseUrl');
    }

    getClientId() {
        return this.get('clientId');
    }
}

const config = new Config();

export default config;
