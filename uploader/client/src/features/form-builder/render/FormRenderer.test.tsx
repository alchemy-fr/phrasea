import {describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import type {FormDefinition, FormValues} from '../types';
import {FormRenderer} from './FormRenderer';

const definition: FormDefinition = {
    version: 1,
    fields: [
        {
            id: 's',
            source: 'layout',
            kind: 'section',
            label: {_: 'About', fr: 'À propos'},
        },
        {
            id: 't',
            source: 'custom',
            key: 'title',
            type: 'text',
            required: true,
            label: {_: 'Title', fr: 'Titre'},
            help: {_: 'The title'},
        },
        {
            id: 'c',
            source: 'custom',
            key: 'dest',
            type: 'choice',
            widget: 'radios',
            label: {_: 'Destination'},
            choices: [
                {value: 'a', label: {_: 'Archive'}},
                {value: 'b', label: {_: 'Blog'}},
            ],
        },
        {
            id: 'h',
            source: 'custom',
            key: 'origin',
            type: 'text',
            hidden: true,
            defaultValue: 'web',
            label: {_: 'Origin'},
        },
    ],
};

function renderForm(lang: string, values: FormValues, onChange = vi.fn()) {
    render(
        <I18nextProvider i18n={createI18n(lang)}>
            <QueryClientProvider client={new QueryClient()}>
                <FormRenderer
                    definition={definition}
                    values={values}
                    onChange={onChange}
                    errors={{title: ['This value is required.']}}
                    loadOptions={async () => ({member: [], hasMore: false})}
                />
            </QueryClientProvider>
        </I18nextProvider>
    );

    return onChange;
}

describe('FormRenderer', () => {
    it('renders the fields in the language of the user', () => {
        renderForm('fr', {title: '', dest: ''});

        expect(screen.getByRole('heading', {name: 'À propos'})).toBeTruthy();
        expect(screen.getByLabelText(/Titre/)).toBeTruthy();
        expect(screen.getByText('The title')).toBeTruthy();
        expect(screen.getByText('This value is required.')).toBeTruthy();
        expect(screen.queryByText('Origin')).toBeNull();
    });

    it('reports the changes', () => {
        const onChange = renderForm('en', {title: '', dest: ''});

        fireEvent.change(screen.getByLabelText(/Title/), {
            target: {value: 'Foo'},
        });
        expect(onChange).toHaveBeenLastCalledWith({title: 'Foo', dest: ''});

        fireEvent.click(screen.getByRole('radio', {name: 'Blog'}));
        expect(onChange).toHaveBeenLastCalledWith({title: '', dest: 'b'});
    });
});
