import React, {
    CSSProperties,
    MouseEvent,
    MouseEventHandler,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';
import {AssetSelectionContext} from '../AssetSelectionContext';
import {Box, Fab, LinearProgress, ListSubheader} from '@mui/material';
import {ResultContext} from './ResultContext';
import Pager, {LayoutEnum} from './Pager';
import SearchBar from './SearchBar';
import SelectionActions from './SelectionActions';
import {Asset} from '../../../types';
import {useTranslation} from 'react-i18next';
import ArrowCircleDownIcon from '@mui/icons-material/ArrowCircleDown';
import {LoadingButton} from '@mui/lab';
import AssetContextMenu from '../Asset/AssetContextMenu';
import {PopoverPosition} from '@mui/material/Popover/Popover';
import {
    OnOpen,
    OnPreviewToggle,
    OnSelectAsset,
    OnUnselectAsset,
} from './Layout/Layout';
import PreviewPopover from '../Asset/PreviewPopover';
import {DisplayContext} from '../DisplayContext';
import {zIndex} from '../../../themes/zIndex';
import AddIcon from '@mui/icons-material/Add';
import UploadModal from '../../Upload/UploadModal';
import {useModals} from '@alchemy/navigation';
import {useNavigateToModal} from '../../Routing/ModalLink';
import {modalRoutes} from '../../../routes.ts';
import {useAuth} from '@alchemy/react-auth';

const gridStyle: CSSProperties = {
    width: '100%',
    height: '100%',
    overflow: 'auto',
};

const linearProgressStyle: CSSProperties = {
    position: 'absolute',
    left: '0',
    right: '0',
    top: '0',
};

const previewEnterDelay = 500;
const previewLeaveDelay = 400;

export const searchMenuId = 'search-menu';

export function getAssetListFromEvent(
    currentSelection: string[],
    id: string,
    pages: Asset[][],
    e?: React.MouseEvent
): string[] {
    if (e?.ctrlKey) {
        return currentSelection.includes(id)
            ? currentSelection.filter(a => a !== id)
            : currentSelection.concat([id]);
    }
    if (e?.shiftKey && currentSelection.length > 0) {
        let boundaries: [
            [number, number] | undefined,
            [number, number] | undefined
        ] = [undefined, undefined];

        for (let i = 0; i < pages.length; ++i) {
            const assets = pages[i];
            for (let j = 0; j < assets.length; ++j) {
                const a = assets[j];
                if (currentSelection.includes(a.id) || id === a.id) {
                    boundaries = [boundaries[0] ?? [i, j], [i, j]];
                }
            }
        }

        const selection = [];
        for (let i = boundaries[0]![0]; i <= boundaries[1]![0]; ++i) {
            const start = i === boundaries[0]![0] ? boundaries[0]![1] : 0;
            const end =
                i === boundaries[1]![0]
                    ? boundaries[1]![1]
                    : pages[i].length - 1;
            for (let j = start; j <= end; ++j) {
                selection.push(pages[i][j].id);
            }
        }

        return selection;
    }

    return [id];
}

export default function AssetResults() {
    const assetSelection = useContext(AssetSelectionContext);
    const resultContext = useContext(ResultContext);
    const authContext = useAuth();
    const navigateToModal = useNavigateToModal();
    const {loading, pages, loadMore} = resultContext;
    const {previewLocked, displayPreview} = useContext(DisplayContext)!;
    const [anchorElMenu, setAnchorElMenu] = React.useState<null | {
        asset: Asset;
        pos: PopoverPosition;
        anchorEl: HTMLElement | undefined;
    }>(null);
    const [previewAnchorEl, setPreviewAnchorEl] = React.useState<null | {
        asset: Asset;
        anchorEl: HTMLElement;
    }>(null);
    const {t} = useTranslation();
    const [layout, setLayout] = useState(LayoutEnum.Grid);
    const timer = useRef<ReturnType<typeof setTimeout>>();
    const {openModal} = useModals();

    useEffect(() => {
        // Force preview close on result change
        setPreviewAnchorEl(null);

        const handler = (e: KeyboardEvent) => {
            if (e.ctrlKey && e.key === 'a') {
                const activeElement = document.activeElement;
                if (
                    activeElement &&
                    ['input', 'select', 'button', 'textarea'].includes(
                        activeElement.tagName.toLowerCase()
                    )
                ) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                assetSelection.selectAssets(
                    resultContext.pages.map(p => p.map(a => a.id)).flat()
                );
            }
        };
        window.addEventListener('keydown', handler);

        return () => {
            window.removeEventListener('keydown', handler);
        };
    }, [resultContext.pages]);

    const onSelect = useCallback<OnSelectAsset>(
        (id, e): void => {
            e?.preventDefault();
            assetSelection.selectAssets(prev => {
                return getAssetListFromEvent(prev, id, resultContext.pages, e);
            });
            // eslint-disable-next-line
        },
        [pages]
    );

    const openUpload = useCallback<
        MouseEventHandler<HTMLButtonElement>
    >((): void => {
        openModal(UploadModal, {
            files: [],
            userId: authContext.user!.id,
        });
    }, [authContext]);

    const onOpen = useCallback<OnOpen>(
        (assetId: string, renditionId: string): void => {
            navigateToModal(modalRoutes.assets.routes.view, {
                id: assetId,
                renditionId,
            });
            // eslint-disable-next-line
        },
        [navigateToModal]
    );

    const onUnselect = useCallback<OnUnselectAsset>(
        (id, e): void => {
            e?.preventDefault();
            assetSelection.selectAssets(p => p.filter(i => i !== id));
            // eslint-disable-next-line
        },
        [pages]
    );

    const onPreviewToggle = useCallback<OnPreviewToggle>(
        (asset, display, anchorEl): void => {
            if (!asset.preview?.file || !displayPreview) {
                return;
            }
            if (timer.current) {
                clearTimeout(timer.current);
            }
            if (!display) {
                if (!previewLocked) {
                    timer.current = setTimeout(() => {
                        setPreviewAnchorEl(null);
                    }, previewLeaveDelay);
                }
                return;
            }

            setPreviewAnchorEl(p => {
                const deferred = !p || previewLocked;

                if (!deferred) {
                    return {
                        asset,
                        anchorEl,
                    };
                }

                timer.current = setTimeout(() => {
                    setPreviewAnchorEl({
                        asset,
                        anchorEl,
                    });
                }, previewEnterDelay);

                return p;
            });
            // eslint-disable-next-line
        },
        [setPreviewAnchorEl, previewLocked, displayPreview]
    );

    const onContextMenuOpen = useCallback(
        (e: MouseEvent<HTMLElement>, asset: Asset, anchorEl?: HTMLElement) => {
            e.preventDefault();
            e.stopPropagation();
            setAnchorElMenu(p => {
                if (p && p.anchorEl === anchorEl) {
                    return null;
                }

                return {
                    asset,
                    pos: {
                        left: e.clientX + 2,
                        top: e.clientY,
                    },
                    anchorEl,
                };
            });
        },
        [setAnchorElMenu]
    );

    const onMenuClose = () => {
        setAnchorElMenu(null);
    };

    return (
        <div
            style={{
                position: 'relative',
                height: '100%',
            }}
        >
            <div style={gridStyle}>
                {loading && (
                    <div style={linearProgressStyle}>
                        <LinearProgress />
                    </div>
                )}
                <div>
                    <ListSubheader
                        id={searchMenuId}
                        component="div"
                        disableGutters={true}
                        sx={() => ({
                            zIndex: zIndex.toolbar,
                        })}
                    >
                        <SearchBar />
                        <SelectionActions
                            layout={layout}
                            onLayoutChange={setLayout}
                        />
                    </ListSubheader>
                    <Pager
                        pages={pages}
                        layout={layout}
                        selectedAssets={assetSelection.selectedAssets}
                        onSelect={onSelect}
                        onOpen={onOpen}
                        onUnselect={onUnselect}
                        onContextMenuOpen={onContextMenuOpen}
                        onPreviewToggle={onPreviewToggle}
                    />
                </div>
                {loadMore ? (
                    <Box
                        sx={{
                            textAlign: 'center',
                            my: 4,
                        }}
                    >
                        <LoadingButton
                            loading={loading}
                            startIcon={<ArrowCircleDownIcon />}
                            onClick={loadMore}
                            variant="contained"
                            color="secondary"
                        >
                            {loading
                                ? t('load_more.button.loading', 'Loading...')
                                : t('load_more.button.loading', 'Load more')}
                        </LoadingButton>
                    </Box>
                ) : (
                    ''
                )}
                {anchorElMenu && (
                    <AssetContextMenu
                        asset={anchorElMenu.asset}
                        anchorPosition={anchorElMenu.pos}
                        anchorEl={anchorElMenu.anchorEl}
                        onClose={onMenuClose}
                    />
                )}
                <PreviewPopover
                    previewLocked={previewLocked}
                    key={previewAnchorEl?.asset.id ?? 'none'}
                    asset={previewAnchorEl?.asset}
                    anchorEl={previewAnchorEl?.anchorEl}
                    displayAttributes={layout === LayoutEnum.Grid}
                />
                {authContext.user && (
                    <Fab
                        onClick={openUpload}
                        color="primary"
                        aria-label="add"
                        sx={theme => ({
                            position: 'absolute',
                            bottom: theme.spacing(2),
                            right: theme.spacing(2),
                        })}
                    >
                        <AddIcon />
                    </Fab>
                )}
            </div>
        </div>
    );
}
