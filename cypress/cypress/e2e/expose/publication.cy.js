import {exposeUrl} from '../lib/urls';

const viewports = [
    [320, 480], // Mobile
    [768, 1024], // Tablet
    [1280, 800], // Desktop
];

/**
 * The media is drawn inside its player, whatever the viewport, and keeps its
 * aspect ratio.
 */
function assertFitted(playerSelector, mediaSelector, ratio) {
    viewports.forEach(([width, height]) => {
        cy.viewport(width, height);
        cy.getBySel('lightbox').should('be.visible');
        cy.getBySel(playerSelector).should($player => {
            const box = $player[0].getBoundingClientRect();
            const media = $player.find(mediaSelector)[0];
            expect(media, mediaSelector).to.exist;
            const rect = media.getBoundingClientRect();

            expect(rect.width, 'width').to.be.greaterThan(0);
            expect(rect.width, 'width').to.be.lte(box.width + 1);
            expect(rect.height, 'height').to.be.lte(box.height + 1);
            expect(rect.width / rect.height, 'ratio').to.be.closeTo(ratio, ratio * 0.03);
        });
    });
}

function assertAsset(title, description) {
    cy.getBySel('asset-legend').should('contain', title).and('contain', description);
}

function next() {
    cy.get('body').trigger('keydown', {key: 'ArrowRight'});
}

describe('Visit publication', () => {
    it('is responsive lightbox', () => {
        cy.viewport(1280, 800);
        cy.visit(`${exposeUrl}/test-pub`);
        cy.contains('Test publication');
        cy.contains('This is a test publication.');

        cy.getBySel('grid-item').eq(0).click();
        cy.getBySel('lightbox').should('be.visible');
        assertAsset('A wide image', 'This is a wide image');
        assertFitted('zoomable-image', 'img', 1000 / 100);

        next();
        assertAsset('A classic image', 'This is a classic image');
        assertFitted('zoomable-image', 'img', 1024 / 683);

        next();
        assertAsset('A tall image', 'This is a tall image');
        assertFitted('zoomable-image', 'img', 100 / 1000);

        cy.get('body').trigger('keydown', {key: 'ArrowLeft'});
        assertAsset('A classic image', 'This is a classic image');

        next();
        assertAsset('A tall image', 'This is a tall image');
        next();
        assertAsset('A test audio', 'This is a test audio');
        cy.getBySel('audio-player').should('be.visible');

        next();
        assertAsset('A classic video', 'This is a classic video');
        cy.get('[data-testid=video-player] video').should($video => {
            expect($video[0].readyState, 'readyState').to.be.gte(1);
        });
        assertFitted('video-player', 'video', 2560 / 1440);

        next();
        assertAsset('A tall video', 'This is a tall video');
        cy.get('[data-testid=video-player] video').should($video => {
            expect($video[0].readyState, 'readyState').to.be.gte(1);
        });
        assertFitted('video-player', 'video', 2160 / 4096);

        next();
        assertAsset('A one page PDF document', 'This is a one page PDF document');
        cy.getBySel('pdf-viewer').should('be.visible');
        cy.getBySel('pdf-page-count', {timeout: 30000}).should('have.text', '/ 1');

        next();
        assertAsset('A two page PDF document', 'This is a two page PDF document');
        cy.getBySel('pdf-page-count', {timeout: 30000}).should('have.text', '/ 2');

        // Zooming scrolls the pages inside the viewer, which keeps its size
        cy.getBySel('pdf-viewer').then($viewer => {
            const initialWidth = $viewer[0].getBoundingClientRect().width;

            for (let i = 0; i < 5; i++) {
                cy.getBySel('pdf-zoom-in').click();
            }

            cy.window().then(win => {
                cy.getBySel('pdf-viewer').should($v => {
                    const box = $v[0].getBoundingClientRect();
                    expect(box.width, 'viewer width').to.be.closeTo(initialWidth, 1);
                    expect(box.right, 'viewer right').to.be.lte(win.innerWidth);

                    const scroll = $v.find('[data-testid=pdf-scroll]')[0];
                    expect(scroll.scrollWidth, 'scroll width').to.be.greaterThan(scroll.clientWidth);
                });
                cy.getBySel('asset-legend')
                    .should('be.visible')
                    .and($legend => {
                        expect($legend[0].getBoundingClientRect().right, 'legend right').to.be.lte(win.innerWidth);
                    });
            });
        });

        cy.getBySel('lightbox-close').click();
        cy.getBySel('lightbox').should('not.exist');
    });
});
