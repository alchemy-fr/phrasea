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

    const assets = publication.assets || [];

    const handleClick = (image: Asset) => {
        setSelectedIndex(image.id === selectedIndex ? null : image.id);
        setCover(image.id === selectedIndex ? null : image);
    };

    const onSetCover = async () => {
        handleSetCover(cover?.id, cover ? getThumbUrl(cover) : undefined);
        closeModal();
        onClose?.();
    };

    React.useEffect(() => {
        if (publication.cover) {
            const coverThumb = assets?.find(c => c.id === publication.cover.id);

            if (coverThumb) {
                setCover(coverThumb);
                setSelectedIndex(coverThumb.id);
            }
        }
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
                {!assets ? (
                    <FullPageLoader backdrop={false} />
                ) : (
                    assets.map(a => (
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
                            {getThumbUrl(a) ? (
                                <img
                                    src={getThumbUrl(a)}
                                    alt={a.title}
                                    style={{
                                        height: rowHeight,
                                    }}
                                />
                            ) : null}
                        </div>
                    ))
                )}
            </Box>
        </AppDialog>
    );
}

export function getThumbUrl(asset: Asset | undefined) {
    return asset?.thumbType && asset.thumbType.startsWith('image/')
        ? asset.thumbUrl
        : asset?.previewType && asset.previewType.startsWith('image/')
          ? asset.previewUrl
          : undefined;
}
