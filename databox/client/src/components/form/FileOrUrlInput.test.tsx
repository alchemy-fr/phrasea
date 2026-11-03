import {describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen} from '@testing-library/react';
import type {ComponentProps} from 'react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {FileOrUrlInput} from './FileOrUrlInput';

const url = 'https://example.com/photo.jpg';

function renderInput(
    props: Partial<ComponentProps<typeof FileOrUrlInput>> = {}
) {
    const onChange = vi.fn();
    render(
        <I18nextProvider i18n={createI18n('en')}>
            <FileOrUrlInput value={{url}} onChange={onChange} {...props} />
        </I18nextProvider>
    );

    return onChange;
}

describe('FileOrUrlInput', () => {
    it('imports the remote file unless told to reference it', () => {
        const onChange = renderInput({allowReference: true});

        const checkbox = screen.getByRole('checkbox');
        expect(checkbox.getAttribute('aria-checked')).toBe('true');

        fireEvent.click(checkbox);
        expect(onChange).toHaveBeenLastCalledWith({url, importFile: false});
    });

    it('keeps the import choice when the URL changes', () => {
        const onChange = renderInput({
            allowReference: true,
            value: {url, importFile: false},
        });

        fireEvent.change(screen.getByPlaceholderText('https://…'), {
            target: {value: 'https://example.com/other.jpg'},
        });
        expect(onChange).toHaveBeenLastCalledWith({
            url: 'https://example.com/other.jpg',
            importFile: false,
        });
    });

    it('offers no reference without allowReference', () => {
        renderInput();

        expect(screen.queryByRole('checkbox')).toBeNull();
    });
});
