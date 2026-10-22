import {describe, expect, it} from 'vitest';
import type {TFunction} from 'i18next';
import {
    AttributeType,
    type Attribute,
    type AttributeDefinition,
} from '@/types/api';
import type {FormatContext} from '@/features/attributes/types/registry';
import {
    getAssetAnnotationsTarget,
    labelAttributeAnnotations,
} from './assetAnnotations';
import {isDefaultAnnotationName} from './annotationNames';
import {AnnotationType, type AssetAnnotation} from './types';

const t = ((
    _key: string,
    defaultValue: string,
    options?: Record<string, unknown>
) =>
    defaultValue.replace(/{{(\w+)}}/g, (_m, name) =>
        String(options?.[name])
    )) as unknown as TFunction;

const ctx: FormatContext = {t, lang: 'en'};

function attribute(
    value: unknown,
    shapes: AssetAnnotation[],
    extra: Partial<Attribute> = {},
    type: string = AttributeType.Entity
): Attribute {
    return {
        id: 'a1',
        definition: {
            id: 'd1',
            name: 'faces',
            displayName: 'Faces',
            type,
        } as AttributeDefinition,
        origin: 'machine',
        multiple: true,
        value,
        assetAnnotations: shapes,
        ...extra,
    } as Attribute;
}

const face: AssetAnnotation = {
    id: 's1',
    type: AnnotationType.Face,
    x: 0.1,
    y: 0.1,
    w: 0.2,
    h: 0.2,
};
const entity = {id: 'e1', value: 'Arthur Dupont', emoji: '🙂'};
const plainEntity = {id: 'e1', value: 'Arthur Dupont'};

describe('labelAttributeAnnotations', () => {
    it('names an unnamed shape after the entity value', () => {
        const [shape] = labelAttributeAnnotations(
            attribute(entity, [face]),
            ctx
        );

        expect(shape).toMatchObject({
            ...face,
            name: '🙂 Arthur Dupont',
            title: 'Faces',
        });
    });

    it('drops a default name and keeps an own name after the value', () => {
        const [defaultNamed, named] = labelAttributeAnnotations(
            attribute(plainEntity, [
                {id: 's1', type: AnnotationType.Rect, name: 'Rectangle #2'},
                {id: 's2', type: AnnotationType.Rect, name: 'Profile'},
            ]),
            ctx
        );

        expect(defaultNamed.name).toBe('Arthur Dupont');
        expect(named.name).toBe('Arthur Dupont · Profile');
    });

    it('keeps the text of a text shape after the value', () => {
        const [shape] = labelAttributeAnnotations(
            attribute(plainEntity, [
                {id: 's1', type: AnnotationType.Text, text: 'hello'},
            ]),
            ctx
        );

        expect(shape.name).toBe('Arthur Dupont · hello');
    });

    it('falls back to the shape label without a value', () => {
        const [shape] = labelAttributeAnnotations(attribute(null, [face]), ctx);

        expect(shape.name).toBe('Face');
        expect(shape.title).toBe('Faces');
    });

    it('formats a text value and shows an invalid value raw', () => {
        const [text] = labelAttributeAnnotations(
            attribute(' Hello ', [face], {}, AttributeType.Text),
            ctx
        );
        const [invalid] = labelAttributeAnnotations(
            attribute('not a date', [face], {invalid: true}, 'date'),
            ctx
        );

        expect(text.name).toBe('Hello');
        expect(invalid.name).toBe('not a date');
    });

    it('uses the name of the definition without display name', () => {
        const attr = attribute(plainEntity, [face]);
        attr.definition = {
            ...attr.definition,
            displayName: undefined,
        } as unknown as AttributeDefinition;

        expect(labelAttributeAnnotations(attr, ctx)[0].title).toBe('faces');
    });
});

describe('isDefaultAnnotationName', () => {
    const rect = (name?: string): AssetAnnotation => ({
        type: AnnotationType.Rect,
        name,
    });

    it('recognizes the default names of the type', () => {
        expect(isDefaultAnnotationName(t, rect('Rectangle #12'))).toBe(true);
        expect(isDefaultAnnotationName(t, rect('Rectangle'))).toBe(false);
        expect(isDefaultAnnotationName(t, rect('Rectangle #2 bis'))).toBe(
            false
        );
        expect(isDefaultAnnotationName(t, rect('Circle #2'))).toBe(false);
        expect(isDefaultAnnotationName(t, rect())).toBe(false);
    });

    it('escapes the translated pattern', () => {
        const translated = ((
            _key: string,
            defaultValue: string,
            options?: Record<string, unknown>
        ) =>
            _key === 'annotation.default_name'
                ? `(${options?.type}) n.${options?.n}`
                : defaultValue) as unknown as TFunction;

        expect(
            isDefaultAnnotationName(translated, rect('(Rectangle) n.3'))
        ).toBe(true);
        expect(isDefaultAnnotationName(translated, rect('Rectangle nx3'))).toBe(
            false
        );
    });
});

describe('getAssetAnnotationsTarget', () => {
    it('keeps the ids of the shapes and the target label', () => {
        const target = getAssetAnnotationsTarget(
            {
                id: 'asset1',
                attributes: [
                    attribute(entity, [face, {type: AnnotationType.Circle}]),
                    attribute(null, [], {id: 'a2'}),
                ],
            },
            ctx,
            () => undefined
        );

        expect(target.key).toBe('asset-asset1');
        expect(target.label).toBe('Attributes');
        expect(target.annotations.map(a => [a.id, a.name, a.title])).toEqual([
            ['a1-s1', '🙂 Arthur Dupont', 'Faces'],
            ['a1-1', '🙂 Arthur Dupont', 'Faces'],
        ]);
    });
});
