import store from './store';
import request from "superagent";
import auth from "./auth";
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

    getUploadBaseURL() {
        return this.get('baseUrl');
    }

    getAvailableLocales() {
        return configData.locales;
    }

    getSignUpURL() {
        return `${this.getAuthBaseURL()}/${i18n.language}/register`;
    }

    getAuthBaseURL() {
        return this.get('authBaseUrl');
    }

    getClientCredential() {
        return {
            clientId: this.get('clientId'),
            clientSecret: this.get('clientSecret'),
        };
    }

    setClientCredential({clientId, clientSecret}) {
        this.set('clientId', clientId);
        this.set('clientSecret', clientSecret);
    }

    setUploadBaseURL(url) {
        this.set('baseUrl', url);
    }

    setAuthBaseURL(url) {
        this.set('authBaseUrl', url);
    }

    devModeEnabled() {
        return configData.devMode;
    }

    getFormSchema() {
        const accessToken = auth.getAccessToken();

        return new Promise((resolve, reject) => {
            request
                .get(config.getUploadBaseURL() + '/form-schema')
                .accept('json')
                .set('Authorization', `Bearer ${accessToken}`)
                .end((err, res) => {
                    if (!auth.isResponseValid(err, res)) {
                        reject(err);
                    }

                    resolve(res.body);
                });
        });
    }

    getBulkData() {
        const accessToken = auth.getAccessToken();

        return new Promise((resolve, reject) => {
            request
                .get(config.getUploadBaseURL() + '/bulk-data')
                .accept('json')
                .set('Authorization', `Bearer ${accessToken}`)
                .end((err, res) => {
                    if (!auth.isResponseValid(err, res)) {
                        reject(err);
                    }

                    resolve(res.body);
                });
        });
    }
}

const config = new Config();

export default config;
