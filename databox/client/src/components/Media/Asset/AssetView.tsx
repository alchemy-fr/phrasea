import React, {FC, useCallback, useMemo, useRef, useState} from 'react';
import {Asset, AssetRendition} from '../../../types';
import {AppDialog, FlexRow} from '@alchemy/phrasea-ui';
import FilePlayer from './FilePlayer';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize';
import {StackedModalProps, useLocation, useParams} from '@alchemy/navigation';
import type {Location} from '@alchemy/navigation';
import {Dimensions, filePlayerRelativeWrapperClassName} from './Players';
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
import {OnActiveAnnotations} from './Attribute/Attributes.tsx';
import AssetViewActions from './Actions/AssetViewActions.tsx';
import {Trans} from 'react-i18next';
import {getMediaBackgroundColor} from '../../../themes/base.ts';
import {useModalFetch} from '../../../hooks/useModalFetch.ts';
import {useChannelRegistration} from "../../../lib/pusher.ts";
import {queryClient} from "../../../lib/query.ts";
import AssetDiscussion from "./AssetDiscussion.tsx";
import {annotationZIndex} from "./Annotations/AssetAnnotationsOverlay.tsx";
import {AssetAnnotation, OnNewAnnotation} from "./Annotations/annotationTypes.ts";
import AssetViewNavigation from "./AssetViewNavigation.tsx";
import {AssetContextState} from "./assetTypes.ts";

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
    const {state} = useLocation() as Location<AssetContextState | undefined>;
    const {id: assetId, renditionId} = useParams();
    const navigateToModal = useNavigateToModal();
    const previousData = useRef<DataTuple | undefined>();
    const [annotations, setAnnotations] = React.useState<
        AssetAnnotation[] | undefined
    >();
    const onNewAnnotationRef = React.useRef<OnNewAnnotation>();

    const queryKey = ['assets', assetId];

    useChannelRegistration(
        `asset-${assetId}`,
        `asset_ingested`,
        () => {
            queryClient.invalidateQueries({queryKey});
        }
    );

    const {data, isSuccess} = useModalFetch({
        queryKey,
        staleTime: 2000,
        refetchOnWindowFocus: false,
        queryFn: () =>
            Promise.all([
                getAsset(assetId!),
                getAssetRenditions(assetId!).then(r => r.result),
            ]),
    });

    const onActiveAnnotations = React.useCallback<OnActiveAnnotations>(annotations => {
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

    const onNewAnnotation: OnNewAnnotation = useCallback((annotation) => {
        onNewAnnotationRef.current?.(annotation);
    }, [onNewAnnotationRef, assetId]);

    const [asset, renditions] = (isSuccess ? data : previousData.current ?? []) as DataTuple;

    React.useEffect(() => {
        if (data) {
            previousData.current = data;
        }
    }, [data, previousData]);

    if (!isSuccess && !previousData.current) {
        if (!open) {
            return null;
        }

        return <FullPageLoader/>;
    }

    const rendition = renditions.find(r => r.id === renditionId);

    const handleRenditionChange = (renditionId: string) => {
        navigateToModal(modalRoutes.assets.routes.view, {
            id: assetId,
            renditionId,
        });
    };

    return (
        <RouteDialog>
            {({onClose}) => (
                <AppDialog
                    modalIndex={modalIndex}
                    open={open}
                    disablePadding={true}
                    sx={{
                        '.MuiDialogTitle-root': {
                            height: headerHeight,
                            maxHeight: headerHeight,
                            zIndex: annotationZIndex + 10,
                        },
                    }}
                    fullScreen={true}
                    title={
                        <FlexRow
                            flexDirection={'row'}
                        >
                            <div>
                                <Trans
                                    i18nKey={'asset_view.edit_asset'}
                                    values={{
                                        name: asset.resolvedTitle,
                                    }}
                                    defaults={
                                        'Edit asset <strong>{{name}}</strong>'
                                    }
                                />
                            </div>
                            <AssetViewNavigation
                                state={state}
                                currentId={assetId!}
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
                            {!integrationOverlay ? (
                                <AssetViewActions
                                    asset={asset}
                                    file={rendition?.file}
                                />
                            ) : (
                                ''
                            )}
                        </FlexRow>
                    }
                    onClose={onClose}
                >
                    {!isSuccess && <FullPageLoader/>}
                    <Box
                        sx={{
                            height: dimensions.height,
                            display: 'flex',
                            flexDirection: 'row',
                            justifyContent: 'space-between',
                        }}
                    >
                        <Box
                            className={filePlayerRelativeWrapperClassName}
                            sx={theme => ({
                                position: 'relative',
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
                            {rendition?.file &&
                                (!integrationOverlay ||
                                    !integrationOverlay.replace) && (
                                    <FilePlayer
                                        onNewAnnotation={onNewAnnotation}
                                        annotations={annotations}
                                        file={rendition.file}
                                        title={asset.title}
                                        dimensions={dimensions}
                                        autoPlayable={false}
                                        controls={true}
                                        zoomEnabled={true}
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
                                onActiveAnnotations={onActiveAnnotations}
                            />

                            <AssetDiscussion
                                asset={asset}
                                onActiveAnnotations={onActiveAnnotations}
                                onNewAnnotationRef={onNewAnnotationRef}
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

type DataTuple = [Asset, AssetRendition[]];
