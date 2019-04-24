import store from './store';

class Auth {
    hasAccessToken() {
        return null !== this.getAccessToken();
    }

    getAccessToken() {
        return store.get('ACCESS_TOKEN') || null;
    }

    setAccessToken(accessToken) {
        return store.set('ACCESS_TOKEN', accessToken);
    }
}

const auth = new Auth();

export default auth;
