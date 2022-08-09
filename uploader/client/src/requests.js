import {oauthClient} from "./oauth";
import request from "superagent";
import config from './config';

export function getFormSchema(targetId) {
    const accessToken = oauthClient.getAccessToken();

    return new Promise((resolve, reject) => {
        request
            .get(`${config.getUploadBaseURL()}/targets/${targetId}/form-schema`)
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

export function getTargets() {
    const accessToken = oauthClient.getAccessToken();

    return new Promise((resolve, reject) => {
        request
            .get(`${config.getUploadBaseURL()}/targets`)
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

export function getTarget(id) {
    const accessToken = oauthClient.getAccessToken();

    return new Promise((resolve, reject) => {
        request
            .get(`${config.getUploadBaseURL()}/targets/${id}`)
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .end((err, res) => {
                if (!oauthClient.isResponseValid(err, res)) {
                    reject({
                        err,
                        res
                    });
                }

                resolve(res.body);
            });
    });
}

export function getTargetParams(targetId) {
    const accessToken = oauthClient.getAccessToken();

    return new Promise((resolve, reject) => {
        request
            .get(`${config.getUploadBaseURL()}/target-params?target=${targetId}`)
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
