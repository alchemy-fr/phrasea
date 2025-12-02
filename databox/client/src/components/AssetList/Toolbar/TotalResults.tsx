import React from 'react';
import {Box} from '@mui/material';
import {Trans, useTranslation} from 'react-i18next';
import {AssetOrAssetContainer} from '../../../types';
import {formatNumber} from '../../../lib/numbers.ts';
import {ParentSelectionContext} from './SelectionActions.tsx';

export type ItemLabelRendererProps = {
    values: {
        total: string;
        selection: string;
    };
    count: number;
    selectedCount: number;
};

export type ItemLabelRenderer = (
    props: ItemLabelRendererProps
) => React.ReactNode;

export type SelectionActionConfigProps = {
    noActions?: boolean;
    itemLabel?: ItemLabelRenderer;
};

type Props<Item extends AssetOrAssetContainer> = {
    loading: boolean;
    total?: number;
    onOpenDebug?: VoidFunction;
    selectionContext: ParentSelectionContext<Item>;
} & SelectionActionConfigProps;

export default function TotalResults<Item extends AssetOrAssetContainer>({
    loading,
    total,
    onOpenDebug,
    selectionContext,
    itemLabel,
}: Props<Item>) {
    const {t, i18n} = useTranslation();
    const {selection, disabledAssets} = selectionContext;
    const realSelectionLength = selection.filter(
        a => !disabledAssets.includes(a)
    ).length;

    const locale = i18n.language;

    const selectionProps: ItemLabelRendererProps = {
        values: {
            total: formatNumber(total ?? 0, locale),
            selection: formatNumber(realSelectionLength, locale),
        },
        count: total ?? 0,
        selectedCount: realSelectionLength,
    };

    return (
        <div>
            {!loading && total !== undefined ? (
                <Box
                    style={onOpenDebug ? {cursor: 'pointer'} : undefined}
                    onClick={onOpenDebug}
                    sx={{
                        strong: {
                            whiteSpace: 'nowrap',
                        },
                    }}
                >
                    {itemLabel ? (
                        itemLabel(selectionProps)
                    ) : selection.length > 0 ? (
                        <Trans
                            i18nKey={
                                'selection_actions.x_result_with_selection'
                            }
                            defaults={`<strong>{{selection}} / {{total}}</strong> result`}
                            tOptions={{
                                defaultValue_other: `<strong>{{selection}} / {{total}}</strong> results`,
                            }}
                            count={selectionProps.count}
                            values={selectionProps.values}
                        />
                    ) : (
                        <Trans
                            i18nKey={'selection_actions.x_result'}
                            defaults={`<strong>{{count}}</strong> result`}
                            tOptions={{
                                defaultValue_other: `<strong>{{total}}</strong> results`,
                            }}
                            count={selectionProps.count}
                            values={selectionProps.values}
                        />
                    )}
                </Box>
            ) : (
                t('common.loading', 'Loading…')
            )}
        </div>
    );
}
