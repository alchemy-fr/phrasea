import React, {FC, useCallback, useMemo, useState} from 'react';
import {Asset, AssetAnnotation, AssetRendition} from '../../../types';
import {AppDialog} from '@alchemy/phrasea-ui';
import FilePlayer from './FilePlayer';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize';
import {StackedModalProps, useParams} from '@alchemy/navigation';
import {Dimensions} from './Players';
import {Box, Select} from '@mui/material';
import FileIntegrations from './FileIntegrations';
import {getAsset} from '../../../api/asset';
import FullPageLoader from '../../Ui/FullPageLoader';
import RouteDialog from '../../Dialog/RouteDialog';
import {getAssetRenditions} from '../../../api/rendition';
import MenuItem from '@mui/material/MenuItem';
import {useNavigateToModal} from '../../Routing/ModalLink';
import {modalRoutes} from '../../../routes';
import {scrollbarWidth} from '../../../constants.ts';
import AssetAttributes from './AssetAttributes.tsx';
import {OnAnnotations} from './Attribute/Attributes.tsx';
import AssetAnnotationsOverlay from './Annotations/AssetAnnotationsOverlay.tsx';
import AssetViewActions from './Actions/AssetViewActions.tsx';
import {Trans} from 'react-i18next';
import {getMediaBackgroundColor} from "../../../themes/base.ts";
import {useModalFetch} from "../../../hooks/useModalFetch.ts";

export type IntegrationOverlayCommonProps = {
    dimensions: Dimensions;
};

type IntegrationOverlay<P extends {} = any> = {
    component: FC<P>;
    props: P;
    replace: boolean;
};

export type SetIntegrationOverlayFunction<P extends {} = any> = (
    component: FC<P & IntegrationOverlayCommonProps> | null,
    props?: P,
    replace?: boolean
) => void;

type Props = {} & StackedModalProps;

export default function AssetView({modalIndex, open}: Props) {
    const menuWidth = 300;
    const headerHeight = 60;
    const {id: assetId, renditionId} = useParams();
    const navigateToModal = useNavigateToModal();
    const [annotations, setAnnotations] = React.useState<
        AssetAnnotation[] | undefined
    >();

    const {data, isSuccess} = useModalFetch({
        queryKey: ['assets', assetId],
        queryFn: () =>
            Promise.all([
                getAsset(assetId!),
                getAssetRenditions(assetId!).then(r => r.result),
            ]),
    });

    const onAnnotations = React.useCallback<OnAnnotations>(annotations => {
        setAnnotations(annotations);
    }, []);

    const winSize = useWindowSize();
    const [integrationOverlay, setIntegrationOverlay] =
        useState<IntegrationOverlay | null>(null);

    const setProxy: SetIntegrationOverlayFunction = useCallback(
        (component, props, replace = false) => {
            if (!component) {
                setIntegrationOverlay(null);
            } else {
                setIntegrationOverlay({
                    component,
                    props,
                    replace,
                });
            }
        },
        [setIntegrationOverlay]
    );

    const dimensions = useMemo<Dimensions>(() => {
        return {
            width: winSize.innerWidth - menuWidth - scrollbarWidth,
            height: winSize.innerHeight - headerHeight - 2,
        };
    }, [winSize]);

    if (!isSuccess) {
        if (!open) {
            return null;
        }
        return <FullPageLoader/>;
    }

    const [asset, renditions] = data as [Asset, AssetRendition[]];
    const rendition = renditions.find(r => r.id === renditionId);

    const handleRenditionChange = (renditionId: string) => {
        navigateToModal(modalRoutes.assets.routes.view, {
            id: assetId,
            renditionId,
        });
    };

    return (
        <RouteDialog>
            {({open, onClose}) => (
                <AppDialog
                    modalIndex={modalIndex}
                    open={open}
                    disablePadding={true}
                    sx={{
                        '.MuiDialogTitle-root': {
                            height: headerHeight,
                            maxHeight: headerHeight,
                        },
                    }}
                    fullScreen={true}
                    title={
                        <>
                            <Trans
                                i18nKey={'asset_view.edit_asset'}
                                values={{
                                    name: asset.resolvedTitle,
                                }}
                                defaults={
                                    'Edit asset <strong>{{name}}</strong>'
                                }
                            />
                            <Select<string>
                                sx={{ml: 2}}
                                label={''}
                                size={'small'}
                                value={rendition?.id}
                                onChange={e =>
                                    handleRenditionChange(e.target.value)
                                }
                            >
                                {renditions.map((r: AssetRendition) => (
                                    <MenuItem key={r.id} value={r.id}>
                                        {r.name}
                                    </MenuItem>
                                ))}
                            </Select>
                            {!integrationOverlay ? <AssetViewActions
                                asset={asset}
                                file={rendition?.file}
                            /> : ''}
                        </>
                    }
                    onClose={onClose}
                >
                    <Box
                        sx={{
                            height: dimensions.height,
                            display: 'flex',
                            flexDirection: 'row',
                            justifyContent: 'space-between',
                        }}
                    >
                        <Box
                            sx={theme => ({
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                justifyContent: 'center',
                                overflowY: 'auto',
                                height: dimensions.height,
                                width: dimensions.width + scrollbarWidth,
                                maxWidth: dimensions.width + scrollbarWidth,
                                backgroundColor: getMediaBackgroundColor(theme),
                            })}
                        >
                            <div
                                style={{
                                    position: 'relative',
                                    width: 'fit-content',
                                    maxHeight: dimensions.height,
                                }}
                            >
                                {annotations && !integrationOverlay ? (
                                    <AssetAnnotationsOverlay
                                        annotations={annotations}
                                    />
                                ) : (
                                    ''
                                )}
                                {rendition?.file &&
                                    (!integrationOverlay ||
                                        !integrationOverlay.replace) && (
                                        <FilePlayer
                                            file={rendition.file}
                                            title={asset.title}
                                            dimensions={dimensions}
                                            autoPlayable={false}
                                            controls={true}
                                        />
                                    )}
                                {integrationOverlay &&
                                    React.createElement(
                                        integrationOverlay.component,
                                        {
                                            dimensions,
                                            ...(integrationOverlay.props || {}),
                                        }
                                    )}
                            </div>
                        </Box>
                        <Box
                            sx={theme => ({
                                width: menuWidth,
                                maxWidth: menuWidth,
                                borderLeft: `1px solid ${theme.palette.divider}`,
                                overflowY: 'auto',
                                height: dimensions.height,
                            })}
                        >
                            <AssetAttributes
                                asset={asset}
                                onAnnotations={onAnnotations}
                            />
                            {rendition?.file ? (
                                <FileIntegrations
                                    key={rendition.file.id}
                                    asset={asset}
                                    file={rendition.file}
                                    setIntegrationOverlay={setProxy}
                                />
                            ) : (
                                ''
                            )}
                        </Box>
                    </Box>
                </AppDialog>
            )}
        </RouteDialog>
    );
}
