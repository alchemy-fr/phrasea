import {describe, expect, it} from 'vitest';
import type {TFunction} from 'i18next';
import {getAnnotationLabel, getAnnotationName} from './annotationNames';
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
