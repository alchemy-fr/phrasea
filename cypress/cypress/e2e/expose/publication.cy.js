import {exposeAdminClientId, exposeAdminClientSecret, exposeUrl} from "../lib/urls";
import {createAsset, createPublication} from "./api";
import {getTokenClientCredentials} from "../lib/api";
import {all} from "../lib/utils";

describe('Visit publication', () => {
    it('passes', () => {
        getTokenClientCredentials(exposeAdminClientId, exposeAdminClientSecret).as('token');

        cy.get('@token').then((token) => {
            createPublication(token, {
                title: 'Publication 1',
                config: {
                    layout: 'grid',
                    enabled: true,
                    publiclyListed: true,
                }
            }).as('publication');
        })

        cy.get('@publication').then((publication) => {
            const assets = ['databox', 'expose', 'uploader'];

            all(assets.map((img, index) => () => createAsset({
                publication_id: publication.id,
                position: index,
                title: `Asset ${img}`,
                description: `Desc ${img}`,
            }, `${img}.png`))).then(() => {
                cy.visit(`${exposeUrl}/${publication.id}`);
                cy.contains(publication.title);

                let lastAsset;
                assets.forEach((asset, index) => {
                    cy.getBySel('grid-gallery-item_viewport').eq(index).click();
                    cy.get('.react-images__positioner').contains(`Desc ${asset}`).should('be.visible');
                    if (lastAsset) {
                        cy.get('.react-images__positioner').contains(`Desc ${lastAsset}`).should('not.be.visible');
                    }
                    cy.get('.react-images__header_button--close').click();
                    lastAsset = asset;
                });

                cy.getBySel('grid-gallery-item_viewport').eq(0).click();
                lastAsset = undefined;
                assets.forEach((asset, index) => {
                    cy.get('.react-images__positioner').contains(`Desc ${asset}`).should('be.visible');
                    if (lastAsset) {
                        cy.get('.react-images__positioner').contains(`Desc ${lastAsset}`).should('not.be.visible');
                    }
                    if (index < assets.length - 1) {
                        cy.get(`[aria-label="Show slide ${(index + 1) % 3 + 1} of 3"]`).click();
                    }
                    lastAsset = asset;
                });
            });
        })
    })
})
