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
import React, {useMemo} from 'react';
import {FormRow} from '@alchemy/react-form';

import SearchGrid from './SearchGrid.tsx';
import ResultProvider from '../../../Media/Search/ResultProvider.tsx';
import SearchProvider from '../../../Media/Search/SearchProvider.tsx';
import DisplayProvider from '../../../Media/DisplayProvider.tsx';

type Props = {
    searchId?: string;
    maxItems?: number;
    size: number;
    containerHeight: number;
    openAsset: boolean;
    displayFacets: boolean;
    actions: {
        basket: boolean;
        export: boolean;
        edit: boolean;
        share: boolean;
        delete: boolean;
        restore: boolean;
        open: boolean;
        move: boolean;
        copy: boolean;
        replace: boolean;
        info: boolean;
    };
};

type ActionKey = keyof Props['actions'];

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
        displayFacets: true,
        containerHeight: 900,
        actions: {
            basket: true,
            export: false,
            edit: false,
            share: true,
            delete: false,
            restore: false,
            open: true,
            move: false,
            copy: false,
            replace: false,
            info: true,
        },
    },
};

export default SearchGridWidget;

function Component({options}: RenderWidgetProps<Props>) {
    const {searchId, displayFacets, containerHeight, actions} = options;

    return (
        <SearchProvider>
            <ResultProvider savedSearch={searchId}>
                <DisplayProvider>
                    <SearchGrid
                        containerHeight={containerHeight}
                        displayFacets={displayFacets}
                        actions={actions ?? {}}
                    />
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

    const actions = useMemo(() => {
        return [
            {
                label: t(
                    'editor.widgets.search_grid.options.actions.basket.label',
                    'Add to basket'
                ),
                key: 'basket' as ActionKey,
            },
            {
                label: t(
                    'editor.widgets.search_grid.options.actions.export.label',
                    'Export'
                ),
                key: 'export' as ActionKey,
            },
            {
                label: t(
                    'editor.widgets.search_grid.options.actions.open.label',
                    'Open'
                ),
                key: 'open' as ActionKey,
            },
            {
                label: t(
                    'editor.widgets.search_grid.options.actions.copy.label',
                    'Copy'
                ),
                key: 'copy' as ActionKey,
            },
            {
                label: t(
                    'editor.widgets.search_grid.options.actions.edit.label',
                    'Edit'
                ),
                key: 'edit' as ActionKey,
            },
            {
                label: t(
                    'editor.widgets.search_grid.options.actions.delete.label',
                    'Delete'
                ),
                key: 'delete' as ActionKey,
            },
            {
                label: t(
                    'editor.widgets.search_grid.options.actions.info.label',
                    'Info'
                ),
                key: 'info' as ActionKey,
            },
            {
                label: t(
                    'editor.widgets.search_grid.options.actions.share.label',
                    'Share'
                ),
                key: 'share' as ActionKey,
            },
            {
                label: t(
                    'editor.widgets.search_grid.options.actions.restore.label',
                    'Restore'
                ),
                key: 'restore' as ActionKey,
            },
            {
                label: t(
                    'editor.widgets.search_grid.options.actions.move.label',
                    'Move'
                ),
                key: 'move' as ActionKey,
            },
            {
                label: t(
                    'editor.widgets.search_grid.options.actions.replace.label',
                    'Replace'
                ),
                key: 'replace' as ActionKey,
            },
        ];
    }, [t]);

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
                <InputLabel>
                    <Checkbox
                        checked={options.displayFacets}
                        onChange={e => {
                            updateOptions({
                                displayFacets: e.target.checked,
                            });
                        }}
                    />
                    {t(
                        'editor.widgets.search_grid.options.displayFacets.label',
                        'Display facets'
                    )}
                </InputLabel>
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
                        'editor.widgets.search_grid.options.containerHeight.label',
                        'Container Height (px)'
                    )}
                    type="number"
                    value={options.containerHeight}
                    onChange={e => {
                        const value = parseInt(e.target.value, 10);
                        if (!isNaN(value)) {
                            updateOptions({
                                containerHeight: value,
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
            <FormRow>
                <InputLabel>
                    {t(
                        'editor.widgets.search_grid.options.actions.label',
                        'Actions'
                    )}
                </InputLabel>
                {actions.map(action => (
                    <InputLabel key={action.key} style={{display: 'block'}}>
                        <Checkbox
                            checked={options.actions?.[action.key] ?? false}
                            onChange={e => {
                                updateOptions({
                                    actions: {
                                        ...(options.actions ?? {}),
                                        [action.key]: e.target.checked,
                                    },
                                });
                            }}
                        />
                        {action.label}
                    </InputLabel>
                ))}
            </FormRow>
        </WidgetOptionsDialogWrapper>
    );
}
