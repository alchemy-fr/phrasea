import http from 'k6/http';
import {group, sleep} from 'k6';

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

    group('Public publication - https://expose.phrasea.local/', function () {
        group('Load page', function () {
            http.get('https://expose.phrasea.local/', {
                headers,
                tags: {
                    service: "expose-client",
                },
            });

            http.get('https://api-expose.phrasea.local/config', {
                headers: apiHeaders,
                tags: {
                    service: "expose-api",
                },
            })

            http.get('https://api-expose.phrasea.local/publications?order[createdAt]=desc', {
                headers: apiHeaders,
                tags: {
                    service: "expose-api",
                },
            })

            sleep(2);
        });

        const publicationRes = http.get('https://api-expose.phrasea.local/publications/load-test', {
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
