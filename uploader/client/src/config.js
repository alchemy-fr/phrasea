import store from './store';
import request from "superagent";
import auth from "./auth";
import i18n from "./locales/i18n";

class Config {
    get(key) {
        return store.get(key) || window.config._env_[key];
    }

    set(key, value) {
        store.set(key, value);
    }

    getUploadBaseURL() {
        return this.get('UPLOADER_BASE_URL');
    }

    getAvailableLocales() {
        return window.config.available_locales;
    }

    getSignUpURL() {
        return `${this.getAuthBaseURL()}/${i18n.language}/register`;
    }

    getAuthBaseURL() {
        return this.get('AUTH_BASE_URL');
    }

    getClientCredential() {
        return {
            clientId: this.get('CLIENT_ID'),
            clientSecret: this.get('CLIENT_SECRET'),
        };
    }

    setClientCredential({clientId, clientSecret}) {
        this.set('CLIENT_ID', clientId);
        this.set('CLIENT_SECRET', clientSecret);
    }

    setUploadBaseURL(url) {
        this.set('UPLOADER_BASE_URL', url);
    }

    setAuthBaseURL(url) {
        this.set('AUTH_BASE_URL', url);
    }

    devModeEnabled() {
        return window.config._env_.DEV_MODE === 'true';
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
