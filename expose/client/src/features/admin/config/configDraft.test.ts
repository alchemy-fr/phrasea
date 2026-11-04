import {describe, expect, it} from 'vitest';
import {Layout, SecurityMethod} from '@/types/api';
import {fromDraft, overrideValue, toDraft, validateDraft} from './configDraft';

describe('configDraft', () => {
    it('leaves unset what is not set', () => {
        const draft = toDraft({});
        expect(draft.enabled).toBeNull();
        expect(draft.securityMethod).toBeNull();
        expect(draft.terms).toBeNull();
        const config = fromDraft(draft);
        expect(config.enabled).toBeNull();
        expect(config.securityMethod).toBeNull();
        expect(config.securityOptions).toEqual([]);
        expect(config.terms).toEqual({enabled: null, text: null, url: null});
    });

    it('keeps the password of the password method only', () => {
        const draft = toDraft({
            securityMethod: SecurityMethod.Password,
            securityOptions: {password: 'secret'},
        });
        expect(draft.password).toBe('secret');
        expect(fromDraft(draft).securityOptions).toEqual({password: 'secret'});
        expect(
            fromDraft({...draft, securityMethod: SecurityMethod.Authentication})
                .securityOptions
        ).toEqual([]);
    });

    it('keeps a public method set on purpose', () => {
        expect(
            toDraft({securityMethod: '' as SecurityMethod}).securityMethod
        ).toBe('');
        expect(
            fromDraft({...toDraft({}), securityMethod: ''}).securityMethod
        ).toBe('');
    });

    it('drops the text and URL of disabled terms', () => {
        const draft = toDraft({
            terms: {enabled: true, text: '<p>x</p>', url: 'https://t'},
        });
        expect(fromDraft(draft).terms).toEqual({
            enabled: true,
            text: '<p>x</p>',
            url: 'https://t',
        });
        expect(
            fromDraft({...draft, terms: {...draft.terms, enabled: false}}).terms
        ).toEqual({enabled: false, text: null, url: null});
    });

    it('overrides with the value of the profile, else a default', () => {
        const profile = toDraft({layout: Layout.Gallery, enabled: null});
        expect(overrideValue('layout', profile)).toBe(Layout.Gallery);
        expect(overrideValue('enabled', profile)).toBe(false);
        expect(overrideValue('securityMethod', profile)).toBe('');
    });

    it('validates the password and the dates', () => {
        const draft = toDraft({});
        expect(
            validateDraft({...draft, securityMethod: SecurityMethod.Password})
        ).toHaveProperty('password');
        expect(
            validateDraft({
                ...draft,
                beginsAt: '2026-02-01T00:00:00Z',
                expiresAt: '2026-01-01T00:00:00Z',
            })
        ).toHaveProperty('expiresAt');
        expect(validateDraft(draft)).toEqual({});
    });
});
