import http from 'k6/http';
import {group, sleep} from 'k6';

const exposeHost = process.env.EXPOSE_HOST || `expose.phrasea.local`;
const exposeApiHost = process.env.EXPOSE_API_HOST || `api-expose.phrasea.local`;
const publicationId = process.env.PUBLICATION_ID;

export const options = {
    insecureSkipTLSVerify: true,
    stages: [
        {duration: '10s', target: 10},
        {duration: '10s', target: 20},
        {duration: '30s', target: 50},
        {duration: '20s', target: 500},
        {duration: '10s', target: 400},
        {duration: '10s', target: 300},
        {duration: '20s', target: 0},
    ],
    // httpDebug: 'true',
    thresholds: {
        'http_req_duration': ['p(99)<500'], // 99% of requests must complete below 200ms
    },
};

export default function main() {
    const headers = {};
    const apiHeaders = {
        accept: 'application/json',
    };

    group(`Public publication - https://${exposeHost}/`, function () {
        group('Load page', function () {
            http.get(`https://${exposeHost}/${publicationId}`, {
                headers,
                tags: {
                    service: "expose-client",
                },
            });

            http.get(`https://${exposeApiHost}/config`, {
                headers: apiHeaders,
                tags: {
                    service: "expose-api",
                },
            })
        });

        const publicationRes = http.get(`https://${exposeApiHost}/publications/${publicationId}`, {
            headers: apiHeaders,
            tags: {
                service: "expose-api",
            },
        })

        const assets = publicationRes.json().assets;
        group('Load thumbs', function () {
            assets.forEach(function (a) {
                http.get(
                    a.asset.thumbUrl,
                    {
                        headers,
                        tags: {
                            service: "minio",
                        },
                    }
                )
            });
        });

        group('Load preview', function () {
            assets.forEach(function (a) {
                http.get(
                    a.asset.previewUrl,
                    {
                        headers,
                        tags: {
                            service: "minio",
                        },
                    }
                )
                sleep(1)
            });
        });
    });
}
