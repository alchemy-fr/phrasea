import {describe, expect, it} from 'vitest';
import {acceptTerms, isTermsAccepted, termsKey} from './terms';

describe('terms', () => {
    it('remembers the acceptance by publication and kind', () => {
        const view = termsKey('view', 'p1');
        const download = termsKey('download', 'p1');
        expect(view).toBe('terms_p_p1');
        expect(download).toBe('terms_pd_p1');
        expect(isTermsAccepted(view)).toBe(false);
        acceptTerms(view);
        expect(isTermsAccepted(view)).toBe(true);
        expect(isTermsAccepted(download)).toBe(false);
        expect(isTermsAccepted(termsKey('view', 'p2'))).toBe(false);
    });
});
