import {useTranslation} from 'react-i18next';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {AppDialog, FullPageLoader} from '@alchemy/phrasea-ui';
import {Button, Box} from '@mui/material';
import {useState} from 'react';
import {useContainerWidth} from '@alchemy/react-hooks/src/useContainerWidth';
import {Classes} from './types';
import {thumbSx} from './asset/AssetIconThumbnail';
import React from 'react';
import {Asset, Publication} from '../../types';
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
} & StackedModalProps;

export default function PublicationCoverDialog({
    publication,
    onClose,
    handleSetCover,
    open,
    rowHeight = 100,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const {containerRef} = useContainerWidth(window.innerWidth);

    const [cover, setCover] = React.useState<Asset | null>(null);
    const [selectedIndex, setSelectedIndex] = useState<string | null>(null);
    const [assetsWithImagePreview, setAssetsWithImagePreview] = useState<
        Asset[]
    >([]);

    const assets = publication.assets || [];

    const handleClick = (image: Asset) => {
        setSelectedIndex(image.id === selectedIndex ? null : image.id);
        setCover(image.id === selectedIndex ? null : image);
    };

    const onSetCover = async () => {
        handleSetCover(cover?.id, cover?.previewUrl);
        closeModal();
        onClose?.();
    };

    const isAnImage = async (url: string | undefined) => {
        if (!url) {
            return false;
        }

        return new Promise(resolve => {
            const img = new Image();
            img.onload = () => resolve(true);
            img.onerror = () => resolve(false);
            img.src = url;
        });
    };

    const isAssetsWithImagePreview = async (assets: Asset[]) => {
        const results = await Promise.all(
            assets.map(async a => {
                const isValid = await isAnImage(a.previewUrl);
                return {...a, isvalidPreview: isValid};
            })
        );
        return results;
    };

    React.useEffect(() => {
        if (publication.cover) {
            const coverThumb = assets?.find(c => c.id === publication.cover.id);

            if (coverThumb) {
                setCover(coverThumb);
                setSelectedIndex(coverThumb.id);
            }
        }

        isAssetsWithImagePreview(assets).then(results => {
            setAssetsWithImagePreview(
                results.filter((a: any) => a.isvalidPreview)
            );
        });
    }, [assets]);

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
                {!assetsWithImagePreview ? (
                    <FullPageLoader backdrop={false} />
                ) : (
                    assetsWithImagePreview.map(a => (
                        <div
                            key={a.id}
                            onClick={() => handleClick(a)}
                            style={{
                                height: rowHeight,
                                margin: '5px',
                                cursor: 'pointer',
                            }}
                            className={classNames({
                                [Classes.thumbContainer]: true,
                                selected: a.id === selectedIndex,
                            })}
                        >
                            <img
                                src={a.previewUrl}
                                alt={a.title}
                                style={{
                                    height: rowHeight,
                                }}
                            />
                        </div>
                    ))
                )}
            </Box>
        </AppDialog>
    );
}
