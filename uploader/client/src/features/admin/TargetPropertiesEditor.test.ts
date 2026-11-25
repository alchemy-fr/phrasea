import {describe, expect, it} from 'vitest';
import type {TargetSettings} from '@/types/api';
import {toTargetPatch, toTargetValues} from './TargetPropertiesEditor';
import {EMPTY_TARGET_VALUES, toTargetInput} from './TargetPropertiesFields';

const settings: TargetSettings = {
    '@id': '/targets/t1',
    '@type': 'target',
    'id': 't1',
    'name': 'Pushed',
    'targetUrl': 'https://example.com/upload',
    'authorizationScheme': 'OAuth',
    'allowedGroups': ['g1'],
    'hasAuthorizationKey': true,
    'hasForm': false,
    'hasData': false,
};

describe('toTargetInput', () => {
    it('sends the empty properties as null', () => {
        expect(toTargetInput({...EMPTY_TARGET_VALUES, name: ' Name '})).toEqual(
            {
                name: 'Name',
                slug: null,
                description: null,
                targetUrl: null,
                authorizationScheme: null,
                authorizationKey: null,
                defaultDestination: null,
                allowedGroups: null,
            }
        );
    });

    it('drops the authorization without a target URL', () => {
        const input = toTargetInput({
            ...EMPTY_TARGET_VALUES,
            name: 'Name',
            authorizationScheme: 'OAuth',
            authorizationKey: 'secret',
        });

        expect(input.authorizationScheme).toBeNull();
        expect(input.authorizationKey).toBeNull();
    });
});

describe('toTargetValues', () => {
    it('starts with an empty authorization key', () => {
        expect(toTargetValues(settings)).toEqual({
            name: 'Pushed',
            slug: '',
            description: '',
            targetUrl: 'https://example.com/upload',
            authorizationScheme: 'OAuth',
            authorizationKey: '',
            defaultDestination: '',
            allowedGroups: ['g1'],
        });
    });
});

describe('toTargetPatch', () => {
    it('leaves the saved key as it is while the field is empty', () => {
        const patch = toTargetPatch(toTargetValues(settings));

        expect(patch).not.toHaveProperty('authorizationKey');
        expect(patch).toMatchObject({
            targetUrl: 'https://example.com/upload',
            authorizationScheme: 'OAuth',
            allowedGroups: ['g1'],
        });
    });

    it('sends a typed key', () => {
        expect(
            toTargetPatch({
                ...toTargetValues(settings),
                authorizationKey: 'new',
            })
        ).toMatchObject({authorizationKey: 'new'});
    });

    it('removes the authorization in the pull mode', () => {
        expect(
            toTargetPatch({
                ...toTargetValues(settings),
                targetUrl: '',
                allowedGroups: [],
            })
        ).toMatchObject({
            targetUrl: null,
            authorizationScheme: null,
            authorizationKey: null,
            allowedGroups: null,
        });
    });
});
