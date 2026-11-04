import {describe, expect, it} from 'vitest';
import type {
    AssetAnnotation,
    Attribute,
    AttributeDefinition,
} from '@/types/api';
import {AttributeBatchActionEnum, AttributeType} from '@/types/api';
import {
    buildAttributeIndex,
    computeBatchActions,
    type DefinitionIndex,
} from './attributeEditorModel';

function definition(
    id: string,
    props: Partial<AttributeDefinition> = {}
): AttributeDefinition {
    return {
        id,
        name: id,
        displayName: id,
        type: AttributeType.Text,
        multiple: false,
        canEdit: true,
        ...props,
    } as AttributeDefinition;
}

function attribute(
    id: string,
    def: AttributeDefinition,
    value: unknown,
    assetAnnotations?: AssetAnnotation[]
): Attribute {
    return {
        id,
        definition: def,
        value,
        multiple: def.multiple,
        origin: 'human',
        ...(assetAnnotations ? {assetAnnotations} : {}),
    } as unknown as Attribute;
}

const rect: AssetAnnotation = {
    id: 'a1',
    type: 'rect',
    x: 0.1,
    y: 0.1,
    w: 0.2,
    h: 0.2,
};

describe('annotations in the attribute editor model', () => {
    const single = definition('d1');
    const multi = definition('d2', {multiple: true});
    const definitions: DefinitionIndex = {d1: single, d2: multi};

    it('keeps the annotations of the stored values', () => {
        const index = buildAttributeIndex(definitions, [
            attribute('x1', single, 'hello', [rect]),
            attribute('x2', multi, 'a'),
        ]);
        expect(index.d1['_']).toMatchObject({
            value: 'hello',
            annotations: [rect],
        });
        expect(index.d2['_']).toEqual([{id: 'x2', value: 'a', invalid: false}]);
    });

    it('sends the annotations of a new or changed value', () => {
        const remote = buildAttributeIndex(definitions, [
            attribute('x1', single, 'hello'),
        ]);
        const current = {
            d1: {_: {...remote.d1['_']!, annotations: [rect]}},
            d2: {_: [{id: 'new-1', value: 'b', annotations: [rect]}]},
        };
        expect(computeBatchActions(current, definitions, remote)).toEqual([
            {
                action: AttributeBatchActionEnum.Set,
                definitionId: 'd1',
                value: 'hello',
                locale: undefined,
                annotations: [rect],
            },
            {
                action: AttributeBatchActionEnum.Add,
                definitionId: 'd2',
                value: 'b',
                locale: undefined,
                annotations: [rect],
            },
        ]);
    });

    it('removes the annotations with an empty list, and nothing else when untouched', () => {
        const remote = buildAttributeIndex(definitions, [
            attribute('x1', single, 'hello', [rect]),
            attribute('x2', multi, 'a', [rect]),
        ]);
        expect(computeBatchActions(remote, definitions, remote)).toEqual([]);

        const current = {
            d1: {_: {...remote.d1['_']!, annotations: []}},
            d2: {
                _: [{...(remote.d2['_'] as any)[0], value: 'a2'}],
            },
        };
        expect(computeBatchActions(current, definitions, remote)).toEqual([
            {
                action: AttributeBatchActionEnum.Set,
                definitionId: 'd1',
                value: 'hello',
                locale: undefined,
                annotations: [],
            },
            {
                action: AttributeBatchActionEnum.Set,
                id: 'x2',
                definitionId: 'd2',
                value: 'a2',
                locale: undefined,
                annotations: [rect],
            },
        ]);
    });
});
