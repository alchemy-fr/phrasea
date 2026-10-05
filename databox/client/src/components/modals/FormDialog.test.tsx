import React from 'react';
import {describe, expect, it, vi} from 'vitest';
import {act, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {FormDialog} from './FormDialog';

const toasts = vi.hoisted(() => ({error: vi.fn()}));
vi.mock('sonner', () => ({toast: toasts}));

function renderDialog(props: Partial<Parameters<typeof FormDialog>[0]> = {}) {
    const onOpenChange = vi.fn();
    renderInAct(
        <I18nextProvider i18n={createI18n('en')}>
            <FormDialog
                open
                onOpenChange={onOpenChange}
                title="Test"
                {...props}
            >
                <span>body</span>
            </FormDialog>
        </I18nextProvider>
    );

    return {onOpenChange};
}

function renderInAct(ui: React.ReactElement) {
    act(() => {
        render(ui);
    });
}

function click(name: string) {
    act(() => {
        screen.getByRole('button', {name}).click();
    });
}

describe('FormDialog', () => {
    it('closes once the submit handler resolves', async () => {
        const {onOpenChange} = renderDialog({onSubmit: () => undefined});
        click('Save');

        await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false));
    });

    it('stays open and reports the error when the submit handler throws', async () => {
        const {onOpenChange} = renderDialog({
            onSubmit: () => {
                throw new Error('nope');
            },
        });
        click('Save');

        await waitFor(() => expect(toasts.error).toHaveBeenCalledWith('nope'));
        expect(onOpenChange).not.toHaveBeenCalled();
    });

    it('keeps the dialog open when the handler returns false', async () => {
        const {onOpenChange} = renderDialog({onSubmit: () => false});
        click('Save');

        await waitFor(() =>
            expect(screen.getByRole('button', {name: 'Save'})).toBeTruthy()
        );
        expect(onOpenChange).not.toHaveBeenCalled();
    });

    it('asks before discarding a dirty form', async () => {
        const {onOpenChange} = renderDialog({dirty: true});
        click('Cancel');

        await waitFor(() => screen.getByText('Discard changes?'));
        expect(onOpenChange).not.toHaveBeenCalled();

        click('Discard');
        expect(onOpenChange).toHaveBeenCalledWith(false);
    });
});
