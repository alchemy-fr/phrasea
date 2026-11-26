import {describe, expect, it} from 'vitest';
import {render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '../test/i18n';
import {TypeToConfirm} from './confirm';

describe('TypeToConfirm', () => {
    it('shows the word to type in a selectable code element', () => {
        render(
            <I18nextProvider i18n={createI18n('fr')}>
                <TypeToConfirm text="DELETE" value="" onChange={() => {}} />
            </I18nextProvider>
        );
        const code = screen.getByText('DELETE');

        expect(code.tagName).toBe('CODE');
        expect(code.className).toContain('select-all');
        expect(code.parentElement!.textContent).toBe(
            'Saisissez DELETE pour confirmer :'
        );
    });
});
