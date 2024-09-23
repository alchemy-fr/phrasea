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
import {useCloseModal, useNavigateToModal} from '../../Routing/ModalLink';
import {modalRoutes} from '../../../routes';
import {scrollbarWidth} from '../../../constants.ts';
import AssetAttributes from './AssetAttributes.tsx';
import {OnAnnotations} from './Attribute/Attributes.tsx';
import AssetAnnotationsOverlay from './Annotations/AssetAnnotationsOverlay.tsx';
import AssetViewActions from './Actions/AssetViewActions.tsx';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import axios from 'axios';

export type IntegrationOverlayCommonProps = {
    dimensions: Dimensions;
};

type IntegrationOverlay<P extends {} = any> = {
    component: FC<P>;
    props: P;
    replace: boolean;
};

export type SetIntegrationOverlayFunction<P extends {} = any> = (
    component: FC<P & IntegrationOverlayCommonProps>,
    props?: P,
    replace?: boolean
) => void;

type Props = {} & StackedModalProps;

export default function AssetView({modalIndex}: Props) {
    const {t} = useTranslation();
    const menuWidth = 300;
    const headerHeight = 60;
    const {id: assetId, renditionId} = useParams();
    const navigateToModal = useNavigateToModal();
    const closeModal = useCloseModal();
    const [annotations, setAnnotations] = React.useState<
        AssetAnnotation[] | undefined
    >();

    const {data, isError, error, isSuccess} = useQuery({
        queryKey: ['assets', assetId],
        queryFn: () =>
            Promise.all([
                getAsset(assetId!),
                getAssetRenditions(assetId!).then(r => r.result),
            ]),
    });

    console.log('data', data);

    React.useEffect(() => {
        if (isError && axios.isAxiosError(error)) {
            console.log('error', error);
            if ([401, 403].includes(error.response?.status ?? 0)) {
                closeModal();
            }
        }
    }, [closeModal, isError]);

    const onAnnotations = React.useCallback<OnAnnotations>(annotations => {
        setAnnotations(annotations);
    }, []);

    const winSize = useWindowSize();
    const [integrationOverlay, setIntegrationOverlay] =
        useState<IntegrationOverlay>();

    const setProxy: SetIntegrationOverlayFunction = useCallback(
        (component, props, replace = false) => {
            setIntegrationOverlay({
                component,
                props,
                replace,
            });
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
        return <FullPageLoader />;
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
                            {t('asset_view.edit_asset', {
                                defaultValue:
                                    'Edit asset <strong>{{name}}</strong>',
                                name: asset.resolvedTitle,
                            })}
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
                            <AssetViewActions
                                asset={asset}
                                file={rendition?.file}
                            />
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
                            sx={{
                                overflowY: 'auto',
                                height: dimensions.height,
                                width: dimensions.width + scrollbarWidth,
                                maxWidth: dimensions.width + scrollbarWidth,
                            }}
                        >
                            <div
                                style={{
                                    position: 'relative',
                                    width: 'fit-content',
                                }}
                            >
                                {annotations ? (
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
