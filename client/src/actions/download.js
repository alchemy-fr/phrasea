
import request from "superagent";
import config from "../store/config";
import auth from "../store/auth";

export function Download(url, callback, errCallback) {
    const accessToken = auth.getAccessToken();

    request
        .post(config.getUploadBaseURL() + '/downloads')
        .accept('json')
        .accept('json')
        .set('Authorization', `Bearer ${accessToken}`)
        .send({
            url,
        })
        .end((err, res) => {
            if (err) {
                errCallback && errCallback();
                console.error(err);
                return;
            }

            if (res.ok) {
                callback();
            }
        });
}
