#!/usr/bin/env node
// Deploys the logos of brand/*.svg into the apps: SVG copies, favicons,
// manifest icons, Open Graph images and the Phrasea logo of the admin and of
// the emails.
//
// Usage: node brand/deploy.mjs [logo...]   (no argument = every logo)

import {readFile, writeFile, mkdir} from 'node:fs/promises';
import {dirname, join, relative} from 'node:path';
import {fileURLToPath} from 'node:url';
import sharp from 'sharp';

const brandDir = dirname(fileURLToPath(import.meta.url));
const rootDir = join(brandDir, '..');

// Same value as background_color in lib/js/core/src/seo/manifest.ts
const BACKGROUND = '#fafafa';
const TRANSPARENT = {r: 0, g: 0, b: 0, alpha: 0};
const ICO_SIZES = [16, 32, 48, 64];

const CLIENTS = ['databox', 'expose', 'uploader', 'dashboard'];
const APIS = ['databox', 'expose', 'uploader'];
const DASHBOARD_TILES = [
    'databox',
    'expose',
    'uploader',
    'dashboard',
    'parade',
    'consent',
    'lend',
    'order',
];

// Emails only show bitmaps, and their header sizes the logo as a square
const PHRASEA_LOGOS = [
    'lib/php/admin-bundle/Resources/public/phrasea-logo.png',
    'lib/php/notifier-bundle/assets/email/phrasea-logo.png',
];

const targets = [
    ...CLIENTS.flatMap(app => {
        const dir = `${app}/client/public`;
        return [
            {logo: app, path: `${dir}/favicon.svg`, render: squareSvg(512)},
            {logo: app, path: `${dir}/favicon.ico`, render: ico},
            {
                logo: app,
                path: `${dir}/apple-touch-icon.png`,
                // iOS shows the transparent pixels in black
                render: onBackground(180, 180, 140),
            },
            {logo: app, path: `${dir}/icons/icon-192.png`, render: icon(192)},
            {logo: app, path: `${dir}/icons/icon-512.png`, render: icon(512)},
            {
                logo: app,
                path: `${dir}/icons/icon-maskable-512.png`,
                // The logo stays within the safe zone of the maskable icons
                // (circle of 80% of the width)
                render: onBackground(512, 512, 308),
            },
            {
                logo: app,
                path: `${dir}/icons/og-image.png`,
                render: onBackground(1200, 630, 420),
            },
        ];
    }),
    ...APIS.flatMap(app => {
        const dir = `${app}/api/public`;
        return [
            {logo: app, path: `${dir}/favicon.ico`, render: ico},
            {logo: app, path: `${dir}/logo192.png`, render: icon(192)},
            {logo: app, path: `${dir}/logo512.png`, render: icon(512)},
        ];
    }),
    ...DASHBOARD_TILES.map(logo => ({
        logo,
        path: `dashboard/client/public/images/${logo}.svg`,
        render: svg => svg,
    })),
    ...PHRASEA_LOGOS.map(path => ({
        logo: 'phrasea',
        path,
        render: icon(180),
    })),
];

/**
 * Renders the logo centered in a transparent width × height box, rasterized
 * at its final size rather than scaled from another bitmap.
 */
async function renderLogo(svg, width, height) {
    const meta = await sharp(svg).metadata();
    const scale = Math.min(width / meta.width, height / meta.height);

    return sharp(svg, {density: meta.density * scale})
        .resize({width, height, fit: 'contain', background: TRANSPARENT})
        .png()
        .toBuffer();
}

function icon(size) {
    return svg => renderLogo(svg, size, size);
}

function onBackground(width, height, logoHeight) {
    return async svg => {
        const logo = await renderLogo(svg, width, logoHeight);
        const composed = await sharp({
            create: {width, height, channels: 3, background: BACKGROUND},
        })
            .composite([{input: logo}])
            .png()
            .toBuffer();

        return sharp(composed).removeAlpha().png().toBuffer();
    };
}

function squareSvg(size) {
    return svg =>
        svg
            .toString()
            .replace(/<svg\b[^>]*>/, tag =>
                tag
                    .replace(/\swidth="[^"]*"/, ` width="${size}"`)
                    .replace(/\sheight="[^"]*"/, ` height="${size}"`)
            );
}

/** ICO file holding one PNG image per size */
async function ico(svg) {
    const images = await Promise.all(
        ICO_SIZES.map(size => renderLogo(svg, size, size))
    );

    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2); // 1 = icon
    header.writeUInt16LE(images.length, 4);

    let offset = header.length + 16 * images.length;
    const entries = images.map((data, i) => {
        const size = ICO_SIZES[i];
        const entry = Buffer.alloc(16);
        entry.writeUInt8(size >= 256 ? 0 : size, 0);
        entry.writeUInt8(size >= 256 ? 0 : size, 1);
        entry.writeUInt8(0, 2); // no palette
        entry.writeUInt8(0, 3);
        entry.writeUInt16LE(1, 4); // color planes
        entry.writeUInt16LE(32, 6); // bits per pixel
        entry.writeUInt32LE(data.length, 8);
        entry.writeUInt32LE(offset, 12);
        offset += data.length;

        return entry;
    });

    return Buffer.concat([header, ...entries, ...images]);
}

async function readIfExists(path) {
    try {
        return await readFile(path);
    } catch (e) {
        if (e.code === 'ENOENT') {
            return null;
        }
        throw e;
    }
}

async function main(requested) {
    const known = [...new Set(targets.map(t => t.logo))];
    const unknown = requested.filter(l => !known.includes(l));
    if (unknown.length > 0) {
        throw new Error(
            `Unknown logo(s): ${unknown.join(', ')}. Available: ${known.join(', ')}`
        );
    }

    const selected =
        requested.length > 0
            ? targets.filter(t => requested.includes(t.logo))
            : targets;

    const sources = new Map();
    for (const {logo} of selected) {
        if (!sources.has(logo)) {
            sources.set(logo, await readFile(join(brandDir, `${logo}.svg`)));
        }
    }

    for (const {logo, path, render} of selected) {
        const output = Buffer.from(await render(sources.get(logo)));
        const file = join(rootDir, path);
        const current = await readIfExists(file);

        if (current && current.equals(output)) {
            process.stdout.write(`  unchanged  ${relative(rootDir, file)}\n`);
            continue;
        }

        await mkdir(dirname(file), {recursive: true});
        await writeFile(file, output);
        process.stdout.write(`  written    ${relative(rootDir, file)}\n`);
    }
}

main(process.argv.slice(2)).catch(e => {
    process.stderr.write(`${e.message}\n`);
    process.exit(1);
});
