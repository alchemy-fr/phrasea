import {describe, expect, it} from 'vitest';
import {createElement, type ReactNode} from 'react';
import {render} from '@testing-library/react';
import {AnnotationType, type AnnotationOptions} from './types';
import {
    defaultShapeSize,
    drawPathData,
    faceStyle,
    getShapeBox,
    getShapeModel,
} from './shapes';
import {boxFromPoints, isPointInBox, normalizeBox, pointsBox} from './geometry';

const media = {width: 1000, height: 500};
const options: AnnotationOptions = {color: '#ff0000', size: 4};

describe('geometry', () => {
    it('puts a box drawn backwards straight', () => {
        expect(normalizeBox({x: 5, y: 6, w: -3, h: -4})).toEqual({
            x: 2,
            y: 2,
            w: 3,
            h: 4,
        });
        expect(boxFromPoints({x: 10, y: 10}, {x: 4, y: 20})).toEqual({
            x: 4,
            y: 10,
            w: 6,
            h: 10,
        });
    });

    it('bounds points and includes the edges of a box', () => {
        const box = pointsBox([
            {x: 1, y: 5},
            {x: 4, y: 3},
            {x: 2, y: 9},
        ]);
        expect(box).toEqual({x: 1, y: 3, w: 3, h: 6});
        expect(isPointInBox({x: 1, y: 3}, box)).toBe(true);
        expect(isPointInBox({x: 4.1, y: 3}, box)).toBe(false);
    });
});

describe('rectangle', () => {
    const model = getShapeModel(AnnotationType.Rect)!;

    it('is created from a drag in relative coordinates, whatever the direction', () => {
        const rect = model.create(
            {x: 300, y: 200},
            {x: 100, y: 100},
            options,
            media
        );
        expect(rect).toMatchObject({
            type: 'rect',
            x: 0.1,
            y: 0.2,
            w: 0.2,
            h: 0.2,
            c: '#ff0000',
            s: 0.004,
        });
        expect(rect).not.toHaveProperty('f');
    });

    it('gets a default size from a click, centered on it', () => {
        const rect = model.create(
            {x: 500, y: 250},
            {x: 501, y: 251},
            options,
            media
        );
        expect(rect.w * media.width).toBe(defaultShapeSize);
        expect(rect.x * media.width).toBe(500 - defaultShapeSize / 2);
    });

    it('moves by a delta in pixels', () => {
        const rect = {type: 'rect', x: 0.1, y: 0.2, w: 0.2, h: 0.2};
        expect(model.move(rect, 100, -50, media)).toMatchObject({
            x: 0.2,
            y: 0.1,
        });
    });

    it('resizes from the handle dragged, crossing the opposite edge', () => {
        const rect = {type: 'rect', x: 0.1, y: 0.2, w: 0.2, h: 0.2};
        expect(model.resize(rect, 'se', {x: 400, y: 300}, media)).toMatchObject(
            {x: 0.1, y: 0.2, w: 0.3, h: 0.4}
        );
        expect(model.resize(rect, 'n', {x: 999, y: 50}, media)).toMatchObject({
            x: 0.1,
            y: 0.1,
            w: 0.2,
            h: 0.3,
        });
        // The west edge dragged past the east one
        expect(model.resize(rect, 'w', {x: 400, y: 0}, media)).toMatchObject({
            x: 0.3,
            w: 0.1,
        });
    });

    it('applies and reads the options, the fill removed when cleared', () => {
        const rect = {
            type: 'rect',
            x: 0.1,
            y: 0.2,
            w: 0.2,
            h: 0.2,
            f: '#00ff00',
        };
        expect(model.toOptions(rect, media)).toEqual({
            color: '#000000',
            size: 0,
            fillColor: '#00ff00',
        });
        const filled = model.applyOptions(
            rect,
            {color: '#0000ff', size: 10, fillColor: '#111111'},
            media
        );
        expect(filled).toMatchObject({c: '#0000ff', s: 0.01, f: '#111111'});
        expect(
            model.applyOptions(filled, {color: '#0000ff', size: 10}, media)
        ).not.toHaveProperty('f');
    });
});

describe('face', () => {
    const model = getShapeModel(AnnotationType.Face)!;

    it('is created from a drag without any style', () => {
        const face = model.create(
            {x: 300, y: 200},
            {x: 100, y: 100},
            {...options, fillColor: '#00ff00'},
            media
        );
        expect(face).toEqual({
            type: 'face',
            x: 0.1,
            y: 0.2,
            w: 0.2,
            h: 0.2,
        });
    });

    it('gets a default size from a click, centered on it', () => {
        const face = model.create(
            {x: 500, y: 250},
            {x: 501, y: 251},
            options,
            media
        );
        expect(face.w * media.width).toBe(defaultShapeSize);
        expect(face.y * media.height).toBe(250 - defaultShapeSize / 2);
        expect(face).not.toHaveProperty('c');
        expect(face).not.toHaveProperty('s');
    });

    it('moves and resizes as a box', () => {
        const face = {type: 'face', x: 0.1, y: 0.2, w: 0.2, h: 0.2};
        expect(model.move(face, 100, -50, media)).toMatchObject({
            x: 0.2,
            y: 0.1,
        });
        expect(model.getHandles(face, media)).toHaveLength(8);
        expect(model.resize(face, 'se', {x: 400, y: 300}, media)).toEqual({
            type: 'face',
            x: 0.1,
            y: 0.2,
            w: 0.3,
            h: 0.4,
        });
    });

    it('has a fixed style and a veil around it', () => {
        const face = {type: 'face', x: 0.1, y: 0.2, w: 0.2, h: 0.2};
        expect(model.fixedStyle).toBe(true);
        expect(model.fillable).toBeFalsy();
        expect(
            model.applyOptions(
                face,
                {color: '#0000ff', size: 10, fillColor: '#111111'},
                media
            )
        ).toEqual(face);
        expect(model.renderMask).toBeDefined();
        expect(getShapeModel(AnnotationType.Rect)!.renderMask).toBeUndefined();
    });

    const oval = {type: 'face', x: 0.1, y: 0.2, w: 0.2, h: 0.2};
    const props = {annotation: oval, media, hitWidth: 14, unscale: 1};
    const renderSvg = (node: ReactNode) =>
        render(createElement('svg', null, node)).container;

    it('grows the oval when emphasized, keeping the stroke on screen', () => {
        const grown = renderSvg(model.render({...props, emphasized: true}));
        const group = grown.querySelector('g') as SVGGElement;
        expect(group.style.transform).toBe(`scale(${faceStyle.expand})`);
        expect(faceStyle.expand).toBe(1.3);
        const stroke = grown.querySelectorAll('ellipse')[1];
        expect(parseFloat(stroke.style.strokeWidth)).toBeCloseTo(
            faceStyle.strokeWidth / faceStyle.expand
        );

        const plain = renderSvg(model.render(props));
        expect((plain.querySelector('g') as SVGGElement).style.transform).toBe(
            'none'
        );
        expect(
            parseFloat(plain.querySelectorAll('ellipse')[1].style.strokeWidth)
        ).toBe(faceStyle.strokeWidth);
    });

    it('darkens the image through a mask whose hole grows with the oval', () => {
        const veil = renderSvg(
            model.renderMask!({...props, emphasized: true, maskId: 'veil-1'})
        );
        const mask = veil.querySelector('mask');
        expect(mask?.getAttribute('id')).toBe('veil-1');
        expect((mask?.querySelector('g') as SVGGElement).style.transform).toBe(
            `scale(${faceStyle.expand})`
        );
        const masked = veil.querySelector('rect[mask]');
        expect(masked?.getAttribute('mask')).toBe('url(#veil-1)');
        expect(masked?.getAttribute('fill')).toBe(faceStyle.mask);
    });
});

describe('circle', () => {
    const model = getShapeModel(AnnotationType.Circle)!;

    it('is centered on the press, the radius relative to the width', () => {
        const circle = model.create(
            {x: 500, y: 250},
            {x: 530, y: 290},
            options,
            media
        );
        expect(circle).toMatchObject({x: 0.5, y: 0.5, r: 0.05});
        expect(getShapeBox(circle, media)).toEqual({
            x: 450,
            y: 200,
            w: 100,
            h: 100,
        });
    });

    it('resizes to the distance of the handle from the center', () => {
        const circle = {type: 'circle', x: 0.5, y: 0.5, r: 0.05};
        expect(model.resize(circle, 'radius', {x: 500, y: 350}, media).r).toBe(
            0.1
        );
    });
});

describe('line', () => {
    const model = getShapeModel(AnnotationType.Arrow)!;

    it('moves both ends and drags one', () => {
        const line = model.create(
            {x: 100, y: 100},
            {x: 300, y: 200},
            options,
            media
        );
        expect(line).toMatchObject({
            type: 'arrow',
            x1: 0.1,
            y1: 0.2,
            x2: 0.3,
            y2: 0.4,
        });
        const moved = model.move(line, 100, 100, media);
        expect([moved.x1, moved.y1, moved.x2, moved.y2]).toEqual(
            [0.2, 0.4, 0.4, 0.6].map(n => expect.closeTo(n))
        );
        expect(
            model.resize(line, 'end', {x: 500, y: 500}, media)
        ).toMatchObject({
            x1: 0.1,
            y1: 0.2,
            x2: 0.5,
            y2: 1,
        });
    });

    it('gets a default length from a click', () => {
        const line = model.create(
            {x: 100, y: 100},
            {x: 100, y: 100},
            options,
            media
        );
        expect((line.x2 - line.x1) * media.width).toBeCloseTo(defaultShapeSize);
    });
});

describe('text', () => {
    const model = getShapeModel(AnnotationType.Text)!;

    it('is created empty at the point, at the font size of the options', () => {
        const text = model.create({x: 0, y: 0}, {x: 100, y: 100}, options, {
            ...media,
        });
        expect(text).toMatchObject({
            type: 'text',
            x: 0.1,
            y: 0.2,
            text: '',
            s: 0.004,
        });
    });

    it('spans its lines, as wide as the longest', () => {
        const one = model.getBox(
            {type: 'text', x: 0.1, y: 0.5, text: 'Hello', s: 0.02},
            media
        );
        const two = model.getBox(
            {type: 'text', x: 0.1, y: 0.5, text: 'Hello\nHello world', s: 0.02},
            media
        );
        expect(two.y).toBe(one.y);
        expect(two.h).toBeCloseTo(20 * 2.2);
        expect(two.w).toBeGreaterThan(one.w);
    });

    it('is bounded above its baseline and resized from its corner', () => {
        const text = {type: 'text', x: 0.1, y: 0.5, text: 'Hello', s: 0.02};
        const box = model.getBox(text, media);
        expect(box.x).toBe(100);
        expect(box.h).toBe(20);
        expect(box.y + box.h).toBeGreaterThan(250 - 1);
        expect(box.w).toBeGreaterThan(0);

        const bigger = model.resize(
            text,
            'size',
            {x: box.x + box.w * 2, y: 0},
            media
        );
        expect(bigger.s).toBeCloseTo(0.04);
    });
});

describe('drawing', () => {
    const model = getShapeModel(AnnotationType.Draw)!;

    it('bounds every path, moved by the offset', () => {
        const drawing = {
            type: 'draw',
            x: 0.1,
            y: 0.2,
            paths: [
                [
                    {x: 0.1, y: 0.5},
                    {x: 0.4, y: 0.3},
                ],
                [{x: 0.2, y: 0.9}],
            ],
        };
        expect(model.getBox(drawing, media)).toEqual({
            x: 200,
            y: 250,
            w: 300,
            h: 300,
        });
        const moved = model.move(drawing, 100, 50, media);
        expect(moved.x).toBeCloseTo(0.2);
        expect(moved.y).toBeCloseTo(0.3);
    });

    it('writes one subpath per stroke', () => {
        expect(
            drawPathData([
                [
                    {x: 1, y: 2},
                    {x: 3.25, y: 4},
                ],
                [{x: 5, y: 6}],
            ])
        ).toBe('M1.0 2.0 L3.3 4.0 M5.0 6.0');
    });
});

describe('target', () => {
    it('has no handle and is bounded by its ticks', () => {
        const model = getShapeModel(AnnotationType.Target)!;
        const target = model.create(
            {x: 0, y: 0},
            {x: 500, y: 250},
            options,
            media
        );
        expect(target).toMatchObject({
            type: 'target',
            x: 0.5,
            y: 0.5,
            s: 0.004,
        });
        expect(model.getHandles(target, media)).toEqual([]);
        const box = model.getBox(target, media);
        expect(box.w).toBe(4 * 7.5 * 2);
        expect(box.x + box.w / 2).toBe(500);
    });

    it('has no model for the time based shapes', () => {
        expect(getShapeModel(AnnotationType.Cue)).toBeUndefined();
        expect(getShapeModel(undefined)).toBeUndefined();
    });
});
