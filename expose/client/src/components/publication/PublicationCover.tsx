import {useTranslation} from 'react-i18next';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {AppDialog, FullPageLoader} from '@alchemy/phrasea-ui';
import {Button, Box} from '@mui/material';
import {useState} from 'react';
import {useContainerWidth} from '@alchemy/react-hooks/src/useContainerWidth';
import {Classes} from './types';
import AssetIconThumbnail, {thumbSx} from './asset/AssetIconThumbnail';
import React from 'react';
import {Publication, ThumbWithDimensions} from '../../types';
import {useThumbs} from '../../hooks/useThumbs';
import {ImageExtended} from './layouts/grid/types';
import {buildLayoutFlat} from './layouts/grid/buildLayout';
import classNames from 'classnames';

type Props = {
    publication: Publication;
    onClose?: () => void;
    handleSetCover: (
        coverId: string | undefined,
        coverSrc: string | undefined
    ) => void;
    open: boolean;
    rowHeight?: number;
    margin?: number;
} & StackedModalProps;

export default function PublicationCover({
    publication,
    onClose,
    handleSetCover,
    open,
    rowHeight = 100,
    margin = 2,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const {containerRef, containerWidth} = useContainerWidth(window.innerWidth);
    const [resolvedThumbs, setResolvedThumbs] =
        React.useState<ImageExtended<ThumbWithDimensions>[]>();
    const [cover, setCover] =
        React.useState<ImageExtended<ThumbWithDimensions> | null>(null);
    const [selectedIndex, setSelectedIndex] = useState<string | null>(null);

    const thumbs = useThumbs({
        publication: publication,
        assets: publication.assets,
    });

    const handleClick = (image: ImageExtended<ThumbWithDimensions>) => {
        setSelectedIndex(image.id === selectedIndex ? null : image.id);
        setCover(image.id === selectedIndex ? null : image);
    };

    const onSetCover = async () => {
        handleSetCover(cover?.id, cover?.src);
        closeModal();
        onClose?.();
    };

    React.useEffect(() => {
        const loadThumbs = async () => {
            const resolvedThumbs = await Promise.all(
                thumbs.map(a => {
                    return new Promise(resolve => {
                        if (!a.src) {
                            return resolve({
                                ...a,
                                width: rowHeight,
                                height: rowHeight,
                            } as ThumbWithDimensions);
                        }

                        const img = new Image();
                        img.onload = () => {
                            resolve({
                                ...a,
                                width: img.width,
                                height: img.height,
                            } as ThumbWithDimensions);
                        };
                        img.onerror = e => {
                            console.error(e);
                            resolve({
                                ...a,
                                width: 100,
                                height: 100,
                            } as ThumbWithDimensions);
                        };
                        img.src = a.src;
                    });
                })
            );

            setResolvedThumbs(
                buildLayoutFlat(
                    resolvedThumbs as ImageExtended<ThumbWithDimensions>[],
                    {
                        containerWidth,
                        rowHeight,
                        margin,
                    }
                )
            );
        };

        loadThumbs();
    }, [thumbs, rowHeight, containerWidth]);

    React.useEffect(() => {
        if (publication.cover) {
            const coverThumb = resolvedThumbs?.find(
                c => c.id === publication.cover.id
            );

            if (coverThumb) {
                setCover(coverThumb);
                setSelectedIndex(coverThumb.id);
            }
        }
    }, [thumbs, resolvedThumbs]);

    return (
        <AppDialog
            maxWidth={'md'}
            onClose={() => {
                closeModal();
                onClose?.();
            }}
            title={t('publication.edit.cover.title', 'Set Cover')}
            open={open}
            actions={({}) => (
                <>
                    <Button
                        variant={'contained'}
                        onClick={onSetCover}
                        disabled={!cover}
                    >
                        {t('publication.edit.cover.set', 'Set Cover')}
                    </Button>
                </>
            )}
            sx={{
                overflow: 'hidden',
            }}
        >
            <Box
                ref={containerRef}
                sx={theme => ({
                    display: 'flex',
                    flexWrap: 'wrap',
                    maxHeight: 300,
                    overflowY: 'auto',
                    [`.${Classes.thumbContainer}`]: {
                        backgroundColor: theme.palette.background.paper,
                        overflow: 'hidden',
                        margin: `2px`,
                        img: {
                            maxWidth: 'none',
                            marginTop: 0,
                            display: 'block',
                        },
                    },
                    ['& .selected']: {
                        border: `5px solid ${theme.palette.primary.dark}`,
                    },
                    ...thumbSx(theme),
                })}
            >
                {!resolvedThumbs ? (
                    <FullPageLoader backdrop={false} />
                ) : (
                    resolvedThumbs.map(t => (
                        <div
                            key={t.id}
                            onClick={() => handleClick(t)}
                            style={{
                                width: t.viewportWidth,
                                height: t.scaledHeight,
                                margin: '5px',
                                cursor: 'pointer',
                            }}
                            className={classNames({
                                [Classes.thumbContainer]: true,
                                selected: t.id === selectedIndex,
                            })}
                        >
                            {t.src ? (
                                <img
                                    src={t.src}
                                    alt={t.alt}
                                    style={{
                                        width: t.scaledWidth,
                                        height: t.scaledHeight,
                                        marginLeft: t.marginLeft,
                                    }}
                                />
                            ) : (
                                <AssetIconThumbnail
                                    style={{
                                        width: t.scaledWidth,
                                        height: t.scaledHeight,
                                        marginLeft: t.marginLeft,
                                    }}
                                    mimeType={t.mimeType}
                                />
                            )}
                        </div>
                    ))
                )}
            </Box>
        </AppDialog>
    );
}
