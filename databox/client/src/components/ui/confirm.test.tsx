import {describe, expect, it} from 'vitest';
import {render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {TypeToConfirm} from './confirm';
import {FilterInput} from './filter-input';

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

describe('FilterInput', () => {
    it('shows a clear button only once filled', () => {
        const {rerender} = render(
            <I18nextProvider i18n={createI18n('en')}>
                <FilterInput value="" onValueChange={() => {}} />
            </I18nextProvider>
        );
        expect(screen.queryByRole('button', {name: 'Clear'})).toBeNull();

        let cleared: string | undefined;
        rerender(
            <I18nextProvider i18n={createI18n('en')}>
                <FilterInput value="foo" onValueChange={v => (cleared = v)} />
            </I18nextProvider>
        );
        screen.getByRole('button', {name: 'Clear'}).click();
        expect(cleared).toBe('');
    });
});
