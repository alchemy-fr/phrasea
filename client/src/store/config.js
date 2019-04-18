import store from './store';

class Config {
    getUploadBaseURL() {
        return store.get('UPLOAD_BASE_URL') || process.env.REACT_APP_UPLOAD_BASE_URL;
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
