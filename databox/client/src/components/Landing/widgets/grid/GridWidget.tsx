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
import {ColorPicker, FormRow} from '@alchemy/react-form';

import AssetGrid from './AssetGrid.tsx';
import ResultProvider from '../../../Media/Search/ResultProvider.tsx';
import SearchProvider from '../../../Media/Search/SearchProvider.tsx';

type Props = {
    searchId?: string;
    maxItems?: number;
    size: number;
    gap: number;
    backgroundColor?: string;
    openAsset: boolean;
};

const GridWidget: WidgetInterface<Props> = {
    name: 'grid',

    getTitle(t: TFunction): string {
        return t('editor.widgets.grid.title', 'Grid');
    },

    component: Component,
    optionsComponent: Options,

    defaultOptions: {
        size: 150,
        maxItems: 50,
        gap: 1,
        openAsset: true,
    },
};

export default GridWidget;

function Component({options}: RenderWidgetProps<Props>) {
    const {searchId} = options;

    return (
        <SearchProvider>
            <ResultProvider savedSearch={searchId}>
                <AssetGrid />
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
                        'editor.widgets.grid.options.searchId.label',
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
                        'editor.widgets.grid.options.maxItems.label',
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
                        'editor.widgets.grid.options.size.label',
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
                <TextField
                    label={t('editor.widgets.grid.options.gap.label', 'Gap')}
                    type="number"
                    value={options.gap}
                    onChange={e => {
                        const value = parseInt(e.target.value, 10);
                        if (!isNaN(value)) {
                            updateOptions({
                                gap: value,
                            });
                        }
                    }}
                />
            </FormRow>
            <FormRow>
                <ColorPicker
                    label={t(
                        'editor.widgets.grid.options.backgroundColor.label',
                        'Background color'
                    )}
                    color={options.backgroundColor}
                    onChange={newColor => {
                        updateOptions({
                            backgroundColor: newColor,
                        });
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
                        'editor.widgets.grid.options.openAsset.label',
                        'Open asset on click'
                    )}
                </InputLabel>
            </FormRow>
        </WidgetOptionsDialogWrapper>
    );
}
