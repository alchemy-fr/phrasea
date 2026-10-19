import {afterEach, describe, expect, it} from 'vitest';
import {cleanup, render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {AttributeType, type BuiltInAttribute} from '@/types/api';
import {useDefinitionsStore} from '@/features/attributes/definitionsStore';
import {useEntitiesStore} from '../entitiesStore';
import {parseAQL} from '../aql/parser';
import {HumanizedExpression} from './SearchConditionChip';

const i18n = createI18n('en');

function renderCondition(query: string) {
    return render(
        <I18nextProvider i18n={i18n}>
            <HumanizedExpression expression={parseAQL(query)!.expression} />
        </I18nextProvider>
    );
}

describe('@similar condition', () => {
    afterEach(() => {
        cleanup();
        useEntitiesStore.setState({index: {}});
        useDefinitionsStore.setState({builtIn: []});
    });

    it('shows the reference asset, its thumbnail and no operator', () => {
        useDefinitionsStore.setState({
            builtIn: [
                {
                    id: '@similar',
                    slug: '@similar',
                    searchSlug: '@similar',
                    name: 'Similar to',
                    type: AttributeType.Asset,
                    builtIn: true,
                    enabled: true,
                } as unknown as BuiltInAttribute,
            ],
        });
        useEntitiesStore.setState({
            index: {
                '/assets/a1': {
                    id: 'a1',
                    name: 'Sunset',
                    thumbnail: {
                        file: {url: 'https://files/a1.png', type: 'image/png'},
                    },
                },
            },
        });

        const {container} = renderCondition('@similar = "a1"');

        expect(container.textContent).toBe('Similar to Sunset');
        expect(
            screen.getByTestId('similar-asset-thumb').getAttribute('src')
        ).toBe('https://files/a1.png');
    });

    it('shows no thumbnail when the asset has none', () => {
        useEntitiesStore.setState({
            index: {'/assets/a2': {id: 'a2', name: 'Report', thumbnail: null}},
        });

        renderCondition('@similar = "a2"');

        expect(screen.queryByTestId('similar-asset-thumb')).toBeNull();
    });
});
