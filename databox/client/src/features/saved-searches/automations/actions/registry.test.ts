import {describe, expect, it} from 'vitest';
import type {TFunction} from 'i18next';
import {actionTypes, getActionDefinition} from './registry';
import {mapViolations} from '../formErrors';

// Default value with interpolation, as i18next does without resources
const t = ((
    _key: string,
    defaultValue: string,
    options?: Record<string, unknown>
) =>
    defaultValue.replace(/{{(\w+)}}/g, (_, k) =>
        String(options?.[k] ?? '')
    )) as unknown as TFunction;

describe('automation actions registry', () => {
    it('declares the action types of the API', () => {
        expect(actionTypes).toEqual([
            'move_to_collection',
            'add_to_collection',
            'attribute',
            'tags',
        ]);
        expect(getActionDefinition('unknown')).toBeUndefined();
    });

    it('starts with incomplete configurations', () => {
        for (const type of actionTypes) {
            const definition = getActionDefinition(type)!;
            expect(definition.isComplete(definition.defaultConfig())).toBe(
                false
            );
        }
    });

    it('completes the configurations', () => {
        expect(
            getActionDefinition('move_to_collection')!.isComplete({
                collectionId: 'c1',
            })
        ).toBe(true);
        expect(
            getActionDefinition('tags')!.isComplete({add: [], remove: ['t1']})
        ).toBe(true);
        expect(
            getActionDefinition('attribute')!.isComplete({
                definitionId: 'd1',
                action: 'delete',
            })
        ).toBe(true);
        expect(
            getActionDefinition('attribute')!.isComplete({
                definitionId: 'd1',
                action: 'replace',
            })
        ).toBe(false);
    });

    it('summarizes the configurations', () => {
        expect(
            getActionDefinition('tags')!.summary(
                {add: ['a', 'b'], remove: ['c']},
                t
            )
        ).toBe('Tags: add 2 tags, remove 1 tags');
        expect(
            getActionDefinition('attribute')!.summary({action: 'replace'}, t)
        ).toBe('Replace text in an attribute');
    });
});

describe('mapViolations', () => {
    it('splits the violations by field and by action', () => {
        expect(
            mapViolations([
                {propertyPath: 'schedule', message: 'Too often'},
                {
                    propertyPath: 'actions[1].config.collectionId',
                    message: 'Not found',
                },
                {propertyPath: 'actions[1].type', message: 'Unsupported'},
                {propertyPath: 'actions', message: 'At least one'},
            ])
        ).toEqual({
            fields: {schedule: 'Too often', actions: 'At least one'},
            actions: {1: {collectionId: 'Not found', type: 'Unsupported'}},
        });
    });
});
