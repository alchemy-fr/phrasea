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

    setUsername(username) {
        return store.set('USERNAME', username);
    }

    getUsername() {
        return store.get('USERNAME') || null;
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

    login(email, password, callback, errCallback) {
        this.setUsername(email);

        this.doLogin(email, password, () => {
            this.triggerEvent('login');
            callback();
        }, errCallback);
    }

    doLogin(email, password, callback, errCallback) {
        const {clientId, clientSecret} = config.getClientCredential();

        request
            .post(config.getAuthBaseURL() + '/oauth/v2/token')
            .accept('json')
            .send({
                username: email,
                password,
                grant_type: 'password',
                client_id: clientId,
                client_secret: clientSecret,
            })
            .end((err, res) => {
                if (err) {
                    if (errCallback) {
                        errCallback(err, res);
                    }
                    return;
                }

                this.setAccessToken(res.body.access_token);
                if (callback) {
                    callback();
                }
            });
    }

    authenticate(callback) {
        if (!this.hasAccessToken()) {
            return;
        }

        request
            .get(config.getUploadBaseURL() + '/me')
            .accept('json')
            .set('Authorization', 'Bearer ' + auth.getAccessToken())
            .end((err, res) => {
                if (!this.isResponseValid(err, res)) {
                    return;
                }

                this.authenticated = true;
                this.triggerEvent('authentication', {user: res.body});
                callback && callback(res.body);
            });
    }

    isResponseValid(err, res) {
        if (err) {
            console.debug(err);
            console.debug(res);
            if (res.statusCode === 401) {
                auth.logout();
            }
            return false;
        }

        return true;
    }
}

const auth = new Auth();

export default auth;
