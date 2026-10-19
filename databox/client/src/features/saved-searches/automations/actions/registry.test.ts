import {describe, expect, it} from 'vitest';
import type {TFunction} from 'i18next';
import {actionTypes, getActionDefinition} from './registry';
import {mapViolations} from '../formErrors';
import {parseEmails} from './EmailReportForm';
import {toHeaders} from './WebhookForm';

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
            'email_report',
            'webhook',
            'trigger_workflow',
            'trash',
            'set_privacy',
        ]);
        expect(getActionDefinition('unknown')).toBeUndefined();
    });

    it('starts with incomplete configurations', () => {
        // Nothing to configure
        const withoutConfig = ['trigger_workflow', 'trash'];
        for (const type of withoutConfig) {
            const definition = getActionDefinition(type)!;
            expect(definition.isComplete(definition.defaultConfig())).toBe(
                true
            );
        }
        for (const type of actionTypes.filter(
            t => !withoutConfig.includes(t)
        )) {
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
        expect(
            getActionDefinition('email_report')!.isComplete({
                users: [],
                groups: [],
                emails: ['a@example.com'],
            })
        ).toBe(true);
        expect(
            getActionDefinition('webhook')!.isComplete({
                url: 'https://hooks.example.com/phrasea',
            })
        ).toBe(true);
        expect(
            getActionDefinition('webhook')!.isComplete({
                url: 'ftp://hooks.example.com',
            })
        ).toBe(false);
        expect(
            getActionDefinition('set_privacy')!.isComplete({privacy: 0})
        ).toBe(true);
        expect(
            getActionDefinition('set_privacy')!.isComplete({privacy: 9})
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
        expect(
            getActionDefinition('email_report')!.summary(
                {users: ['u1'], groups: ['g1'], emails: ['a@example.com']},
                t
            )
        ).toBe('Email report to 3 recipients');
        expect(
            getActionDefinition('webhook')!.summary(
                {url: 'https://hooks.example.com:8443/phrasea'},
                t
            )
        ).toBe('Call hooks.example.com:8443');
        expect(
            getActionDefinition('set_privacy')!.summary({privacy: 5}, t)
        ).toBe('Privacy: Public');
    });

    it('parses the email addresses', () => {
        expect(
            parseEmails(' a@example.com\nb@example.com, c@example.com;\n\n')
        ).toEqual(['a@example.com', 'b@example.com', 'c@example.com']);
        expect(parseEmails('  ')).toEqual([]);
    });
});

describe('webhook headers', () => {
    it('keeps the named headers, the last one winning', () => {
        expect(
            toHeaders([
                {name: ' Authorization ', value: 'Bearer x'},
                {name: '', value: 'ignored'},
                {name: 'X-Token', value: '1'},
                {name: 'X-Token', value: '2'},
            ])
        ).toEqual({'Authorization': 'Bearer x', 'X-Token': '2'});
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
