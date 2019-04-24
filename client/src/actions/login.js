import request from "superagent";
import config from "../store/config";
import auth from "../store/auth";

export function Login(email, password, callback) {
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

            auth.setAccessToken(res.body.access_token);
            callback();
        });
}

export function Authenticate(callback)
{
    if (auth.hasAccessToken()) {
        request
            .get(config.getUploadBaseURL() + '/me')
            .set('Authorization', 'Bearer ' + auth.getAccessToken())
            .set('accept', 'json')
            .end((err, res) => {
                if (err) {
                    throw new Error(err);
                }

                callback(res.body);
            });
    }
}
