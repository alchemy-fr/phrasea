import store from './store';
import request from "superagent";
import config from "./config";

class Auth {
    listeners = {};
    authenticated = false;

    hasAccessToken() {
        return null !== this.getAccessToken();
    }

    getAccessToken() {
        return store.get('ACCESS_TOKEN') || null;
    }

    setAccessToken(accessToken) {
        return store.set('ACCESS_TOKEN', accessToken);
    }

    isAuthenticated() {
        return this.authenticated;
    }

    logout() {
        this.authenticated = false;
        this.setAccessToken('');
        this.triggerEvent('logout');
    }

    registerListener(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }

    triggerEvent(type, event = {}) {
        event.type = type;
        this.listeners[type].forEach(func => func(event));
    }

    login(email, password, callback) {
        const {clientId, clientSecret} = config.getClientCredential();

        request
            .post(config.getUploadBaseURL() + '/oauth/v2/token')
            .send({
                username: email,
                password,
                grant_type: 'password',
                client_id: clientId,
                client_secret: clientSecret,
            })
            .set('accept', 'json')
            .end((err, res) => {
                if (err) {
                    throw new Error(err);
                }

                this.setAccessToken(res.body.access_token);
                this.triggerEvent('login');
                callback();
            });
    }

    authenticate(callback) {
        if (!this.hasAccessToken()) {
            return;
        }

        request
            .get(config.getUploadBaseURL() + '/me')
            .set('Authorization', 'Bearer ' + auth.getAccessToken())
            .set('accept', 'json')
            .end((err, res) => {
                if (err) {
                    this.logout();
                    throw new Error(err);
                }

                this.authenticated = true;
                this.triggerEvent('authentication', {user: res.body});
                callback && callback(res.body);
            });
    }
}

const auth = new Auth();

export default auth;
