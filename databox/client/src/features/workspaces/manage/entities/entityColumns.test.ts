import {describe, expect, it} from 'vitest';
import type {TFunction} from 'i18next';
import type {AttributeEntity, EntityList} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {
    buildEntityColumns,
    cellValueOf,
    entityPatchFromCell,
} from './entityColumns';

const t = ((_key: string, fallback?: string) =>
    fallback ?? _key) as unknown as TFunction;

const entity = {
    id: 'e1',
    value: 'Summer',
    note: 'season',
    emoji: '☀️',
    color: '#ff0000',
    translations: {fr: 'Été'},
    synonyms: {en: ['Warm', 'Hot']},
    createdAt: '',
    updatedAt: '',
    status: AttributeEntityStatus.Pending,
} as unknown as AttributeEntity;

const noop = () => {};
const columnIds = (list: Partial<EntityList>) =>
    buildEntityColumns({
        list: {id: 'l1', name: 'L', ...list} as EntityList,
        locales: ['en', 'fr'],
        t,
        onApprove: noop,
        onReject: noop,
        onPreview: noop,
        onEdit: noop,
        onDelete: noop,
    }).map(c => c.id);

describe('entityColumns', () => {
    it('only has the columns enabled on the list', () => {
        expect(columnIds({})).toEqual([
            'select',
            'value',
            'note',
            'status',
            'usage',
            'updatedAt',
            'actions',
        ]);
        expect(
            columnIds({
                withEmojis: true,
                withColors: true,
                withImages: true,
                withTranslations: true,
                withSynonyms: true,
            })
        ).toEqual([
            'select',
            'value',
            'note',
            'emoji',
            'color',
            'image',
            'translations.en',
            'translations.fr',
            'synonyms.en',
            'synonyms.fr',
            'status',
            'usage',
            'updatedAt',
            'actions',
        ]);
    });

    it('reads the cell values', () => {
        expect(cellValueOf(entity, 'value')).toBe('Summer');
        expect(cellValueOf(entity, 'translations.fr')).toBe('Été');
        expect(cellValueOf(entity, 'translations.en')).toBe('');
        expect(cellValueOf(entity, 'synonyms.en')).toBe('Warm, Hot');
        expect(cellValueOf(entity, 'status')).toBe('1');
    });

    it('builds the patch of a cell', () => {
        expect(entityPatchFromCell(entity, 'value', ' Winter ')).toEqual({
            value: 'Winter',
        });
        expect(entityPatchFromCell(entity, 'value', '  ')).toBeNull();
        expect(entityPatchFromCell(entity, 'note', ' ')).toEqual({note: null});
        expect(entityPatchFromCell(entity, 'emoji', '')).toEqual({emoji: null});
        expect(entityPatchFromCell(entity, 'color', '')).toEqual({color: null});
        expect(entityPatchFromCell(entity, 'color', '#00ff00')).toEqual({
            color: '#00ff00',
        });
        expect(entityPatchFromCell(entity, 'status', '0')).toEqual({
            status: AttributeEntityStatus.Approved,
        });
        expect(
            entityPatchFromCell(entity, 'translations.en', 'Summer EN')
        ).toEqual({
            translations: {fr: 'Été', en: 'Summer EN'},
        });
        expect(entityPatchFromCell(entity, 'translations.fr', '')).toEqual({
            translations: {},
        });
        expect(
            entityPatchFromCell(entity, 'synonyms.fr', 'Chaud, , Tiède')
        ).toEqual({
            synonyms: {en: ['Warm', 'Hot'], fr: ['Chaud', 'Tiède']},
        });
        expect(entityPatchFromCell(entity, 'synonyms.en', '')).toEqual({
            synonyms: {},
        });
    });
});
