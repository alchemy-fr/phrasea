import {exposeApiUrl} from "../lib/urls";

export function createPublication(token, data) {
    return cy.request({
        url: `${exposeApiUrl}/publications`,
        method: 'POST',
        body: data,
        auth: {
            bearer: token,
        },
    }).then(res => {
        return res.body;
    });
}

export function createAsset(data, src) {
    return cy.get('@token').then((token) => {
        return cy.fixture(src, 'binary')
            .then((file) => Cypress.Blob.binaryStringToBlob(file))
            .then((blob) => {
                const formData = new FormData();
                formData.append('file', blob, basename(src));
                Object.entries(data).forEach(([k, v]) => {
                    formData.append(k, v);
                });

                cy.request({
                    url: `${exposeApiUrl}/assets `,
                    method: 'POST',
                    body: formData,
                    auth: {
                        bearer: token,
                    },
                }).then(res => {
                    return res.body;
                });
            });
    });
}

function basename(path) {
    return path.split('/').reverse()[0];
}
