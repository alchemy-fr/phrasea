
class Config {
    get(key) {
        return window.config._env_[key];
    }

    getApiBaseUrl() {
        return this.get('EXPOSE_BASE_URL');
    }
}

const config = new Config();

export default config;
