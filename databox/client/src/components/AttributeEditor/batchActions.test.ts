import {getBatchActions} from './batchActions';
import {Asset, Attribute, AttributeDefinition} from '../../types.ts';
import {AttributeDefinitionIndex, BatchAttributeIndex} from './types.ts';
import {
    AttributeBatchAction,
    AttributeBatchActionEnum,
    AttributeType,
} from '../../api/types.ts';
import {NO_LOCALE} from '../Media/Asset/Attribute/constants.ts';

describe('getBatchActions', () => {
    it('returns actions', () => {
        const attrDefBase = {
            sortable: true,
            searchable: true,
            editable: true,
            editableInGui: true,
            enabled: true,
            canEdit: true,
        };
        const attrDefText: AttributeDefinition = {
            ...attrDefBase,
            id: 'text',
            name: 'text',
            slug: 'text',
            searchSlug: 'text',
            multiple: false,
            fieldType: AttributeType.Text,
        } as unknown as AttributeDefinition;

        const attrDefTextMulti: AttributeDefinition = {
            ...attrDefText,
            id: 'textmulti',
            name: 'text multi',
            slug: 'textmulti',
            searchSlug: 'textmulti',
            multiple: true,
        } as unknown as AttributeDefinition;

        const attrDefGeoPoint: AttributeDefinition = {
            ...attrDefBase,
            id: 'geopoint',
            name: 'geopoint',
            slug: 'geopoint',
            searchSlug: 'geopoint',
            multiple: false,
            fieldType: AttributeType.GeoPoint,
        } as unknown as AttributeDefinition;

        const capabilities = {
            canEdit: true,
            canDelete: true,
            canEditPermissions: true,
        };

        const attrBase = {
            capabilities: capabilities,
        };

        const attrText1: Attribute = {
            ...attrBase,
            id: 't1',
            locale: 'en',
            value: 'value1',
            definition: attrDefText,
            multiple: false,
        } as unknown as Attribute;

        const attrTextMulti1: Attribute = {
            ...attrBase,
            id: 'tm1',
            locale: 'en',
            value: 'valuem1',
            definition: attrDefTextMulti,
            multiple: true,
        } as unknown as Attribute;

        const attrTextMulti2: Attribute = {
            ...attrTextMulti1,
            id: 'tm2',
            value: 'valuem2',
        } as unknown as Attribute;

        const attrGeoPoint: Attribute = {
            ...attrBase,
            id: 'geo1',
            value: {lat: 1, lon: 2},
            definition: attrDefGeoPoint,
            multiple: false,
        } as unknown as Attribute;

        const asset1: Asset = {
            id: 'asset1',
            attributes: [
                attrText1,
                attrTextMulti1,
                attrTextMulti2,
                attrGeoPoint,
            ],
        } as unknown as Asset;

        const assets = [asset1];
        const initialAttributes = {
            [attrDefText.id]: {[asset1.id]: {en: 'value1'}},
            [attrDefTextMulti.id]: {[asset1.id]: {en: ['valuem1', 'valuem2']}},
            [attrDefGeoPoint.id]: {
                [asset1.id]: {[NO_LOCALE]: {lat: 1, lon: 2}},
            },
        };
        const definitions = {
            [attrDefText.id]: attrDefText,
            [attrDefTextMulti.id]: attrDefTextMulti,
            [attrDefGeoPoint.id]: attrDefGeoPoint,
        } as unknown as AttributeDefinitionIndex;

        const createToKey = (fieldType: AttributeType) => {
            if (fieldType === AttributeType.GeoPoint) {
                return (v: any) => {
                    if (!v) {
                        return '';
                    }
                    if (typeof v === 'string') {
                        return v;
                    }
                    return `${v.lat}, ${v.lon}`;
                };
            } else {
                return (v: any) => v?.toString() ?? '';
            }
        };

        const e = (
            attrs: BatchAttributeIndex<any>,
            expectedActions: AttributeBatchAction[]
        ) => {
            const actions = getBatchActions<any>(
                assets,
                initialAttributes,
                attrs,
                definitions,
                createToKey
            );
            expect(actions).toEqual(expectedActions);
        };

        e(
            {
                [attrDefText.id]: {[asset1.id]: {en: 'value1'}},
                [attrDefTextMulti.id]: {
                    [asset1.id]: {en: ['valuem1', 'valuem2']},
                },
                [attrDefGeoPoint.id]: {
                    [asset1.id]: {[NO_LOCALE]: {lat: 1, lon: 2}},
                },
            },
            []
        );
        e(
            {
                [attrDefText.id]: {[asset1.id]: {en: 'value2'}},
                [attrDefTextMulti.id]: {
                    [asset1.id]: {en: ['valuem1', 'valuem2']},
                },
                [attrDefGeoPoint.id]: {
                    [asset1.id]: {[NO_LOCALE]: {lat: 1, lon: 2}},
                },
            },
            [
                {
                    action: AttributeBatchActionEnum.Set,
                    assets: [asset1.id],
                    locale: 'en',
                    value: 'value2',
                    definitionId: attrDefText.id,
                },
            ]
        );

        e(
            {
                [attrDefText.id]: {},
                [attrDefTextMulti.id]: {
                    [asset1.id]: {en: ['valuem1', 'valuem2']},
                },
                [attrDefGeoPoint.id]: {
                    [asset1.id]: {[NO_LOCALE]: {lat: 1, lon: 2}},
                },
            },
            [
                {
                    action: AttributeBatchActionEnum.Delete,
                    assets: [asset1.id],
                    locale: 'en',
                    value: 'value1',
                    ids: [attrText1.id],
                    definitionId: attrDefText.id,
                },
            ]
        );

        e(
            {
                [attrDefText.id]: {[asset1.id]: {en: ''}},
                [attrDefTextMulti.id]: {
                    [asset1.id]: {en: ['valuem1', 'valuem2']},
                },
                [attrDefGeoPoint.id]: {
                    [asset1.id]: {[NO_LOCALE]: {lat: 1, lon: 2}},
                },
            },
            [
                {
                    action: AttributeBatchActionEnum.Delete,
                    assets: [asset1.id],
                    locale: 'en',
                    value: 'value1',
                    ids: [attrText1.id],
                    definitionId: attrDefText.id,
                },
            ]
        );

        e(
            {
                [attrDefText.id]: {[asset1.id]: {en: ''}},
                [attrDefTextMulti.id]: {[asset1.id]: {en: ['valuem2']}},
                [attrDefGeoPoint.id]: {
                    [asset1.id]: {[NO_LOCALE]: {lat: 1, lon: 2}},
                },
            },
            [
                {
                    action: AttributeBatchActionEnum.Delete,
                    assets: [asset1.id],
                    locale: 'en',
                    value: 'valuem1',
                    ids: [attrTextMulti1.id],
                    definitionId: attrDefTextMulti.id,
                },
                {
                    action: AttributeBatchActionEnum.Delete,
                    assets: [asset1.id],
                    locale: 'en',
                    value: 'value1',
                    ids: [attrText1.id],
                    definitionId: attrDefText.id,
                },
            ]
        );

        e(
            {
                [attrDefText.id]: {[asset1.id]: {en: ''}},
                [attrDefTextMulti.id]: {[asset1.id]: {en: ['valuem2']}},
                [attrDefGeoPoint.id]: {[asset1.id]: {}},
            },
            [
                {
                    action: AttributeBatchActionEnum.Delete,
                    assets: [asset1.id],
                    locale: 'en',
                    value: 'valuem1',
                    ids: [attrTextMulti1.id],
                    definitionId: attrDefTextMulti.id,
                },
                {
                    action: AttributeBatchActionEnum.Delete,
                    assets: [asset1.id],
                    locale: 'en',
                    value: 'value1',
                    ids: [attrText1.id],
                    definitionId: attrDefText.id,
                },
                {
                    action: AttributeBatchActionEnum.Delete,
                    assets: [asset1.id],
                    value: {lat: 1, lon: 2},
                    ids: [attrGeoPoint.id],
                    definitionId: attrDefGeoPoint.id,
                },
            ]
        );
    });
});
