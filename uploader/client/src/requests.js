import {oauthClient} from "./oauth";
import request from "superagent";
import config from './config';

export function getFormSchema() {
    const accessToken = oauthClient.getAccessToken();

    return new Promise((resolve, reject) => {
        request
            .get(config.getUploadBaseURL() + '/form-schema')
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .end((err, res) => {
                if (!oauthClient.isResponseValid(err, res)) {
                    reject(err);
                }

                resolve(res.body);
            });
    });
}

export function getBulkData() {
    const accessToken = oauthClient.getAccessToken();

    return new Promise((resolve, reject) => {
        request
            .get(config.getUploadBaseURL() + '/bulk-data')
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .end((err, res) => {
                if (!oauthClient.isResponseValid(err, res)) {
                    reject(err);
                }

                resolve(res.body);
            });
    });
}
