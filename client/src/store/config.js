import store from './store';

class Config {
    getUploadBaseURL() {
        return store.get('UPLOAD_BASE_URL') || window._env_.UPLOAD_BASE_URL;
    }

    getClientCredential() {
        return {
            clientId: store.get('CLIENT_ID') || window._env_.CLIENT_ID,
            clientSecret: store.get('CLIENT_SECRET') || window._env_.CLIENT_SECRET,
        };
    }

    setClientCredential({clientId, clientSecret}) {
        store.set('CLIENT_ID', clientId);
        store.set('CLIENT_SECRET', clientSecret);
    }

    setUploadBaseURL(url) {
        store.set('UPLOAD_BASE_URL', url);
    }

    devModeEnabled() {
        return window._env_.DEV_MODE === 'true';
    }
}

const config = new Config();

export default config;
