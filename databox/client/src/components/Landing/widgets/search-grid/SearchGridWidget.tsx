import {TFunction} from '@alchemy/i18n';
import {
    RenderWidgetOptionsProps,
    RenderWidgetProps,
    WidgetInterface,
} from '../widgetTypes.ts';
import {Checkbox, InputLabel, TextField} from '@mui/material';
import WidgetOptionsDialogWrapper from '../components/WidgetOptionsDialogWrapper.tsx';
import SavedSearchSelect from '../../../Form/SavedSearchSelect.tsx';
import {useTranslation} from 'react-i18next';
import React from 'react';
import {FormRow} from '@alchemy/react-form';

import SearchGrid from './SearchGrid.tsx';
import ResultProvider from '../../../Media/Search/ResultProvider.tsx';
import SearchProvider from '../../../Media/Search/SearchProvider.tsx';
import DisplayProvider from '../../../Media/DisplayProvider.tsx';

type Props = {
    searchId?: string;
    maxItems?: number;
    size: number;
    openAsset: boolean;
};

const SearchGridWidget: WidgetInterface<Props> = {
    name: 'search_grid',

    getTitle(t: TFunction): string {
        return t('editor.widgets.search_grid.title', 'Search Grid');
    },

    component: Component,
    optionsComponent: Options,

    defaultOptions: {
        size: 150,
        maxItems: 50,
        openAsset: true,
    },
};

export default SearchGridWidget;

function Component({options}: RenderWidgetProps<Props>) {
    const {searchId} = options;

    return (
        <SearchProvider>
            <ResultProvider savedSearch={searchId}>
                <DisplayProvider>
                    <SearchGrid />
                </DisplayProvider>
            </ResultProvider>
        </SearchProvider>
    );
}

function Options({
    options,
    updateOptions,
    ...props
}: RenderWidgetOptionsProps<Props>) {
    const {t} = useTranslation();

    return (
        <WidgetOptionsDialogWrapper {...props}>
            <FormRow>
                <SavedSearchSelect
                    label={t(
                        'editor.widgets.search_grid.options.searchId.label',
                        'Saved Search'
                    )}
                    value={options.searchId as any}
                    onChange={newValue => {
                        updateOptions({
                            searchId: newValue?.value,
                        });
                    }}
                />
            </FormRow>
            <FormRow>
                <TextField
                    label={t(
                        'editor.widgets.search_grid.options.maxItems.label',
                        'Max items'
                    )}
                    type="number"
                    value={options.maxItems}
                    onChange={e => {
                        const value = parseInt(e.target.value, 10);
                        if (!isNaN(value)) {
                            updateOptions({
                                maxItems: value,
                            });
                        }
                    }}
                />
            </FormRow>
            <FormRow>
                <TextField
                    label={t(
                        'editor.widgets.search_grid.options.size.label',
                        'Size (px)'
                    )}
                    type="number"
                    value={options.size}
                    onChange={e => {
                        const value = parseInt(e.target.value, 10);
                        if (!isNaN(value)) {
                            updateOptions({
                                size: value,
                            });
                        }
                    }}
                />
            </FormRow>
            <FormRow>
                <InputLabel>
                    <Checkbox
                        checked={options.openAsset}
                        onChange={e => {
                            updateOptions({
                                openAsset: e.target.checked,
                            });
                        }}
                    />
                    {t(
                        'editor.widgets.search_grid.options.openAsset.label',
                        'Open asset on click'
                    )}
                </InputLabel>
            </FormRow>
        </WidgetOptionsDialogWrapper>
    );
}
