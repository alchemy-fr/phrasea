import React, {FC, useCallback, useMemo, useRef, useState} from 'react';
import {Asset, AssetRendition} from '../../../../types.ts';
import {AppDialog} from '@alchemy/phrasea-ui';
import {MemoizedFilePlayer} from '../FilePlayer.tsx';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts';
import {StackedModalProps, useParams, useLocation} from '@alchemy/navigation';
import {Dimensions, filePlayerRelativeWrapperClassName} from '../Players';
import {Box, Typography} from '@mui/material';
import FileIntegrations from '../FileIntegrations.tsx';
import {getAsset, getAssets} from '../../../../api/asset.ts';
import FullPageLoader from '../../../Ui/FullPageLoader.tsx';
import RouteDialog from '../../../Dialog/RouteDialog.tsx';
import {getAssetRenditions} from '../../../../api/rendition.ts';
import {scrollbarWidth} from '../../../../constants.ts';
import AssetAttributes from '../AssetAttributes.tsx';
import {useTranslation} from 'react-i18next';
import {getMediaBackgroundColor} from '../../../../themes/base.ts';
import {useModalFetch} from '../../../../hooks/useModalFetch.ts';
import {useChannelRegistration} from '../../../../lib/pusher.ts';
import {queryClient} from '../../../../lib/query.ts';
import AssetDiscussion from '../AssetDiscussion.tsx';
import {
    AssetAnnotation,
    AssetAnnotationRef,
} from '../Annotations/annotationTypes.ts';
import AssetViewHeader from './AssetViewHeader.tsx';
import {annotationZIndex} from '../Annotations/common.ts';
import {
    AttachmentType,
    MessageFormRef,
} from '../../../Discussion/discussion.ts';
import {useBindAnnotationMessage} from './useBindAnnotationMessage.ts';
import AssetViewInfo from '../AssetViewInfo.tsx';
import {ApiCollectionResponse} from '../../../../api/hydra.ts';
import StoryCarousel, {storyCarouselHeight} from './StoryCarousel.tsx';

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
    const menuWidth = 400;
    const headerHeight = 60;
    let heightRest = headerHeight;
    const {id: assetId, renditionId} = useParams();
    const {state} = useLocation();
    const assetAnnotationsRef: AssetAnnotationRef = useRef(null);
    const messageFormRef: MessageFormRef = useRef(null);
    const previousData = useRef<DataTuple | undefined>();
    const [annotations, setAnnotations] = React.useState<
        AssetAnnotation[] | undefined
    >();
    const {t} = useTranslation();
    const queryKey = ['assets', assetId];
    const [storyAssets, setStoryAssets] =
        React.useState<ApiCollectionResponse<Asset>>();
    const [currentStoryAssetId, setCurrentStoryAssetId] = React.useState<
        string | undefined
    >(state?.storyAssetId);

    useChannelRegistration(`asset-${assetId}`, `asset_ingested`, () => {
        queryClient.invalidateQueries({queryKey});
    });

    const {data, isSuccess, isError} = useModalFetch({
        queryKey,
        staleTime: 2000,
        refetchOnWindowFocus: false,
        queryFn: () =>
            Promise.all([
                getAsset(assetId!),
                getAssetRenditions(assetId!).then(r => r.result),
            ]),
    });

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

    const [[asset, renditions], rendition] = (
        isSuccess
            ? [
                  data,
                  renditionId
                      ? data[1].find(r => r.id === renditionId)!
                      : data[1][0],
              ]
            : (previousData.current ?? [[], undefined])
    ) as DataTuple;

    React.useEffect(() => {
        setAnnotations(undefined);
        if (data) {
            previousData.current = [
                data,
                renditionId
                    ? data[1].find(r => r.id === renditionId)!
                    : data[1][0],
            ];
        }
    }, [data, previousData, renditionId]);

    const isStory = Boolean(asset?.storyCollection);
    if (isStory) {
        heightRest += storyCarouselHeight;
    }

    const dimensions = useMemo<Dimensions>(() => {
        return {
            width: winSize.innerWidth - menuWidth - scrollbarWidth,
            height: winSize.innerHeight - heightRest - 2,
        };
    }, [winSize]);

    React.useEffect(() => {
        if (asset?.storyCollection) {
            getAssets({
                parents: [asset.storyCollection.id],
            }).then(setStoryAssets);
        } else {
            setStoryAssets(undefined);
        }
    }, [asset]);

    const {
        onNewAnnotation,
        onUpdateAnnotation,
        onDeleteAnnotation,
        onAttachmentClick,
        onMessageDelete,
        onAttachmentRemove,
        onMessageFocus,
    } = useBindAnnotationMessage({
        assetAnnotationsRef,
        messageFormRef,
    });

    if (!isSuccess && !isError && !previousData.current) {
        if (!open) {
            return null;
        }

        return <FullPageLoader />;
    }

    const panelHeight = winSize.innerHeight - headerHeight;

    const currentStoryAsset = currentStoryAssetId
        ? storyAssets?.result.find(a => a.id === currentStoryAssetId)
        : undefined;
    const displayedAsset = currentStoryAsset || asset;

    const displayedRenditionFile = currentStoryAsset
        ? currentStoryAsset.original?.file ||
          currentStoryAsset.preview?.file ||
          currentStoryAsset.thumbnail?.file
        : rendition?.file;

    return (
        <RouteDialog>
            {({onClose}) => (
                <AppDialog
                    disableEscapeKeyDown={true}
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
                        !isError ? (
                            <AssetViewHeader
                                asset={asset}
                                rendition={rendition}
                                renditions={renditions}
                                displayActions={!integrationOverlay}
                                currentStoryAsset={currentStoryAsset}
                            />
                        ) : (
                            <></>
                        )
                    }
                    onClose={onClose}
                >
                    {!isSuccess && !isError && <FullPageLoader />}
                    {!isError ? (
                        <>
                            <Box
                                sx={{
                                    display: 'flex',
                                    flexDirection: 'row',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <div
                                    style={{
                                        height: panelHeight,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        width:
                                            dimensions.width + scrollbarWidth,
                                    }}
                                >
                                    <Box
                                        className={
                                            filePlayerRelativeWrapperClassName
                                        }
                                        sx={theme => ({
                                            position: 'relative',
                                            display: 'flex',
                                            flexDirection: 'column',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            overflowY: 'auto',
                                            height: dimensions.height,
                                            width:
                                                dimensions.width +
                                                scrollbarWidth,
                                            maxWidth:
                                                dimensions.width +
                                                scrollbarWidth,
                                            backgroundColor:
                                                getMediaBackgroundColor(theme),
                                        })}
                                    >
                                        {Boolean(displayedRenditionFile) &&
                                            (!integrationOverlay ||
                                                !integrationOverlay.replace) && (
                                                <MemoizedFilePlayer
                                                    assetAnnotationsRef={
                                                        assetAnnotationsRef
                                                    }
                                                    onNewAnnotation={
                                                        onNewAnnotation
                                                    }
                                                    onUpdateAnnotation={
                                                        onUpdateAnnotation
                                                    }
                                                    onDeleteAnnotation={
                                                        onDeleteAnnotation
                                                    }
                                                    annotations={annotations}
                                                    file={
                                                        displayedRenditionFile!
                                                    }
                                                    title={displayedAsset.title}
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
                                                    ...(integrationOverlay.props ||
                                                        {}),
                                                }
                                            )}
                                    </Box>

                                    {isStory ? (
                                        <StoryCarousel
                                            assets={storyAssets}
                                            selectedAsset={displayedAsset}
                                            story={asset}
                                            onAssetClick={a => {
                                                setCurrentStoryAssetId(a.id);
                                            }}
                                        />
                                    ) : null}
                                </div>

                                <Box
                                    sx={theme => ({
                                        width: menuWidth,
                                        maxWidth: menuWidth,
                                        borderLeft: `1px solid ${theme.palette.divider}`,
                                        overflowY: 'auto',
                                        height: panelHeight,
                                    })}
                                >
                                    <AssetAttributes
                                        asset={displayedAsset}
                                        assetAnnotationsRef={
                                            assetAnnotationsRef
                                        }
                                    />

                                    <AssetViewInfo asset={displayedAsset} />

                                    <AssetDiscussion
                                        asset={displayedAsset}
                                        onFocus={onMessageFocus}
                                        onMessageDelete={onMessageDelete}
                                        onAttachmentClick={onAttachmentClick}
                                        onAttachmentRemove={onAttachmentRemove}
                                        normalizeAttachment={a => {
                                            if (
                                                a.type ===
                                                AttachmentType.Annotation
                                            ) {
                                                return {
                                                    ...a,
                                                    data: {
                                                        ...(a.data as AssetAnnotation),
                                                        editable: undefined,
                                                    },
                                                };
                                            }

                                            return a;
                                        }}
                                        messageFormRef={messageFormRef}
                                    />
                                    {displayedRenditionFile ? (
                                        <FileIntegrations
                                            key={displayedRenditionFile.id}
                                            asset={asset}
                                            file={displayedRenditionFile}
                                            assetAnnotationsRef={
                                                assetAnnotationsRef
                                            }
                                            setIntegrationOverlay={setProxy}
                                        />
                                    ) : (
                                        ''
                                    )}
                                </Box>
                            </Box>
                        </>
                    ) : (
                        <Box
                            sx={{
                                display: 'flex',
                                justifyContent: 'center',
                                alignItems: 'center',
                                height: dimensions.height,
                            }}
                        >
                            <Typography variant={'h6'}>
                                {t('asset.not_found', 'Asset not found')}
                            </Typography>
                        </Box>
                    )}
                </AppDialog>
            )}
        </RouteDialog>
    );
}

type DataTuple = [[Asset, AssetRendition[]], AssetRendition];
