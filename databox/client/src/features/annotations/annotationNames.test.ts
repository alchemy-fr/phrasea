import {describe, expect, it} from 'vitest';
import type {TFunction} from 'i18next';
import {
    formatAnnotationTime,
    getAnnotationLabel,
    getAnnotationName,
} from './annotationNames';
import {labelAttributeAnnotations} from './assetAnnotations';
import {AttributeType, type Attribute} from '@/types/api';
import {AnnotationType, type AssetAnnotation} from './types';

const t = ((
    _key: string,
    defaultValue: string,
    options?: Record<string, unknown>
) =>
    defaultValue.replace(/{{(\w+)}}/g, (_m, name) =>
        String(options?.[name])
    )) as unknown as TFunction;

const rect: AssetAnnotation = {
    type: AnnotationType.Rect,
    x: 0.1,
    y: 0.1,
    w: 0.2,
    h: 0.2,
};

describe('getAnnotationLabel', () => {
    it('names a shape of an image after its name, its text or its type', () => {
        expect(getAnnotationLabel(t, {...rect, name: 'Logo'})).toBe('Logo');
        expect(
            getAnnotationLabel(t, {
                type: AnnotationType.Text,
                x: 0,
                y: 0,
                text: 'Hello',
            })
        ).toBe('Hello');
        expect(getAnnotationLabel(t, rect)).toBe('Rectangle');
    });

    it('tells the page of a shape of a document', () => {
        expect(getAnnotationLabel(t, {...rect, name: 'Logo', page: 2})).toBe(
            'Logo · p. 2'
        );
        expect(getAnnotationLabel(t, {...rect, page: 1})).toBe(
            'Rectangle · p. 1'
        );
        // On the page itself, the name is enough
        expect(getAnnotationName(t, {...rect, name: 'Logo', page: 2})).toBe(
            'Logo'
        );
    });

    it('tells the time of a shape or a cue of a video', () => {
        expect(getAnnotationLabel(t, {...rect, name: 'Logo', t: 5.9})).toBe(
            'Logo · 0:05'
        );
        expect(getAnnotationLabel(t, {type: AnnotationType.Cue, t: 754})).toBe(
            'Cue · 12:34'
        );
        expect(getAnnotationLabel(t, {...rect, t: 0})).toBe('Rectangle · 0:00');
    });

    it('names the shapes of a value once, with their page', () => {
        const attribute = {
            id: 'a1',
            definition: {
                id: 'd1',
                name: 'signature',
                type: AttributeType.Text,
            },
            value: 'Signed',
            assetAnnotations: [{...rect, page: 3}],
        } as unknown as Attribute;

        const [shape] = labelAttributeAnnotations(attribute, {t, lang: 'en'});

        expect(shape.name).toBe('Signed');
        expect(getAnnotationLabel(t, shape)).toBe('Signed · p. 3');
    });
});

describe('formatAnnotationTime', () => {
    it('shows minutes and seconds, hours from an hour', () => {
        expect(formatAnnotationTime(0)).toBe('0:00');
        expect(formatAnnotationTime(9.99)).toBe('0:09');
        expect(formatAnnotationTime(65)).toBe('1:05');
        expect(formatAnnotationTime(3599)).toBe('59:59');
        expect(formatAnnotationTime(3600)).toBe('1:00:00');
        expect(formatAnnotationTime(3725)).toBe('1:02:05');
        expect(formatAnnotationTime(-3)).toBe('0:00');
    });
});
