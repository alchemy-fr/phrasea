import request from "superagent";
import config from "../config";
import {oauthClient} from "../oauth";

export function Download(url, callback, errCallback) {
    const accessToken = oauthClient.getAccessToken();

    request
        .post(config.getUploadBaseURL() + '/downloads')
        .accept('json')
        .accept('json')
        .set('Authorization', `Bearer ${accessToken}`)
        .send({
            url,
        })
        .end((err, res) => {
            if (!oauthClient.isResponseValid(err, res) || err) {
                errCallback && errCallback();
                console.error(err);
                return;
            }

            if (res.ok) {
                callback();
            }
        });
}
