import React, {useCallback} from 'react';
import {Badge, Box, Checkbox, Tooltip} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {TSelectionContext} from '../../../context/AssetSelectionContext';
import {AssetOrAssetContainer} from '../../../types';
import {ActionsContext, ReloadFunc} from '../types';
import WithSelectionActions from './WithSelectionActions.tsx';

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

export type ParentSelectionContext<Item extends AssetOrAssetContainer> =
    TSelectionContext<Item>;

export type SelectionActionsProps<Item extends AssetOrAssetContainer> = {
    loading: boolean;
    total?: number;
    pages: Item[][];
    reload?: ReloadFunc;
    onOpenDebug?: VoidFunction;
    actionsContext: ActionsContext<Item>;
    selectionContext: ParentSelectionContext<Item>;
} & SelectionActionConfigProps;

type Props<Item extends AssetOrAssetContainer> =
    {} & SelectionActionsProps<Item>;

export default function SelectionActions<Item extends AssetOrAssetContainer>({
    total,
    pages,
    noActions,
    selectionContext,
    ...props
}: Props<Item>) {
    const {t} = useTranslation();
    const {selection, setSelection} = selectionContext;
    const selectionLength = selection.length;
    const hasSelection = selectionLength > 0;
    const allSelected =
        hasSelection &&
        selectionLength ===
            pages.reduce((currentCount, row) => currentCount + row.length, 0);

    const toggleSelectAll = useCallback(() => {
        setSelection(previous => (previous.length > 0 ? [] : pages.flat()));
    }, [pages]);

    const selectAllDisabled = (total ?? 0) === 0;

    return (
        <>
            <Box
                sx={theme => ({
                    display: 'flex',
                    alignItems: 'center',
                    gap: 1,
                    [theme.breakpoints.down('md')]: {
                        '.MuiButton-startIcon': {
                            display: 'none',
                        },
                    },
                })}
            >
                <Tooltip
                    title={
                        hasSelection
                            ? t('asset_actions.unselect_all', 'Unselect all')
                            : t('asset_actions.select_all', 'Select all')
                    }
                >
                    <span>
                        <Badge
                            anchorOrigin={{
                                horizontal: 'right',
                                vertical: 'top',
                            }}
                            slotProps={{
                                badge: {
                                    style: {
                                        right: 6,
                                        top: 10,
                                    },
                                },
                                root: {
                                    style: {
                                        marginRight: 6,
                                    },
                                },
                            }}
                            badgeContent={selectionLength}
                            color="secondary"
                        >
                            <Checkbox
                                indeterminate={!allSelected && hasSelection}
                                checked={allSelected}
                                disabled={selectAllDisabled}
                                onChange={() => toggleSelectAll()}
                            />
                        </Badge>
                    </span>
                </Tooltip>

                {!noActions && selectionLength > 0 ? (
                    <WithSelectionActions
                        selectionContext={selectionContext}
                        {...props}
                    />
                ) : null}
            </Box>
        </>
    );
}
