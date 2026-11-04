import {describe, expect, it} from 'vitest';
import {render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {FilterInput} from './filter-input';

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
