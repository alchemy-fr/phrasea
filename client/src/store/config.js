import store from './store';

class Config {
    getUploadBaseURL() {
        return store.get('UPLOAD_BASE_URL') || process.env.REACT_APP_UPLOAD_BASE_URL;
    }

    getClientCredential() {
        return {
            clientId: store.get('CLIENT_ID') || process.env.REACT_APP_CLIENT_ID,
            clientSecret: store.get('CLIENT_SECRET') || process.env.REACT_APP_CLIENT_SECRET,
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
        return process.env.REACT_APP_DEV_MODE === 'true';
    }
}

const config = new Config();

export default config;
