import {describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen} from '@testing-library/react';
import type {ComponentProps} from 'react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {ENTITY_IMAGE_LIMITS} from '@/lib/uploadLimits';
import {FilePicker} from './FilePicker';

const MiB = 1024 * 1024;

function fileOf(name: string, type: string, size: number): File {
    const file = new File(['x'], name, {type});
    Object.defineProperty(file, 'size', {value: size});

    return file;
}

function renderPicker(props: Partial<ComponentProps<typeof FilePicker>> = {}) {
    const onChange = vi.fn();
    render(
        <I18nextProvider i18n={createI18n('en')}>
            <FilePicker
                {...ENTITY_IMAGE_LIMITS}
                value={undefined}
                onChange={onChange}
                current={null}
                uploadLabel="Upload"
                removeLabel="Remove"
                removedLabel="Removed"
                testId="picker"
                {...props}
            />
        </I18nextProvider>
    );

    return onChange;
}

const pick = (file: File) =>
    fireEvent.change(
        screen
            .getByTestId('picker')
            .querySelector('input[type=file]') as HTMLInputElement,
        {target: {files: [file]}}
    );
const error = () => screen.queryByTestId('picker-error');

describe('FilePicker', () => {
    it('tells the accepted types and the limits above the button', () => {
        renderPicker();
        expect(screen.getByTestId('picker-help').textContent).toBe(
            'JPEG, PNG, WebP, GIF, or SVG · 20 MiB max (SVG: 100 KiB)'
        );
    });

    it('shows the given help instead', () => {
        renderPicker({help: 'Custom help'});
        expect(screen.getByTestId('picker-help').textContent).toBe(
            'Custom help'
        );
    });

    it('has no help nor check without a limit', () => {
        const onChange = renderPicker({maxSize: undefined});
        expect(screen.queryByTestId('picker-help')).toBeNull();
        const file = fileOf('a.png', 'image/png', 30 * MiB);
        pick(file);
        expect(onChange).toHaveBeenCalledWith(file);
    });

    it('accepts a valid file', () => {
        const onChange = renderPicker();
        const file = fileOf('a.png', 'image/png', MiB);
        pick(file);
        expect(onChange).toHaveBeenCalledWith(file);
        expect(error()).toBeNull();
    });

    it('refuses a file too large without calling onChange', () => {
        const onChange = renderPicker();
        pick(fileOf('big.jpg', 'image/jpeg', 21 * MiB));
        expect(onChange).not.toHaveBeenCalled();
        expect(error()?.getAttribute('role')).toBe('alert');
        expect(error()?.textContent).toBe(
            '"big.jpg" is too large (21 MiB): the limit is 20 MiB.'
        );
    });

    it('refuses an SVG over its own limit', () => {
        const onChange = renderPicker();
        pick(fileOf('logo.svg', 'image/svg+xml', 200 * 1024));
        expect(onChange).not.toHaveBeenCalled();
        expect(error()?.textContent).toBe(
            '"logo.svg" is too large (200 KiB): the limit is 100 KiB.'
        );
    });

    it('refuses a type out of accept', () => {
        const onChange = renderPicker();
        pick(fileOf('doc.pdf', 'application/pdf', 10));
        expect(onChange).not.toHaveBeenCalled();
        expect(error()?.textContent).toContain('is not an accepted file type');
    });

    it('clears the error on the next valid file', () => {
        const onChange = renderPicker();
        pick(fileOf('big.jpg', 'image/jpeg', 21 * MiB));
        expect(error()).not.toBeNull();
        pick(fileOf('ok.jpg', 'image/jpeg', MiB));
        expect(error()).toBeNull();
        expect(onChange).toHaveBeenCalledTimes(1);
    });
});
