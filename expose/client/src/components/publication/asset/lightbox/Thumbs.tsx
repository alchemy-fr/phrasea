import {Box} from '@mui/material';
import {LightboxClasses} from './types.ts';
import AssetIconThumbnail, {thumbSx} from '../AssetIconThumbnail.tsx';
import classnames from 'classnames';
import React, {useEffect, useRef} from 'react';
import {Asset, Thumb} from '../../../../types.ts';
import {Link} from '@alchemy/navigation';

type Props = {
    asset?: Asset;
    thumbs: Thumb[];
    thumbHeight: number;
    thumbPadding: number;
    isDark?: boolean;
};

export default function Thumbs({
    asset,
    thumbs,
    thumbHeight,
    thumbPadding,
    isDark,
}: Props) {
    const thumbsContainerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const container = thumbsContainerRef.current;
        if (!container) {
            return;
        }
        const onWheel = (e: WheelEvent) => {
            if (e.deltaY === 0) return;
            e.preventDefault();
            container.scrollLeft += e.deltaY;
        };
        container.addEventListener('wheel', onWheel, {passive: false});

        return () => {
            container.removeEventListener('wheel', onWheel);
        };
    }, [thumbsContainerRef]);

    useEffect(() => {
        if (asset) {
            thumbsContainerRef.current
                ?.querySelector(`#t_${asset.id}`)
                ?.scrollIntoView({
                    behavior: 'smooth',
                    inline: 'center',
                    block: 'nearest',
                });
        }
    }, [asset]);

    return (
        <>
            <Box
                ref={thumbsContainerRef}
                sx={_theme => ({
                    maxWidth: '100vw',
                    overflow: 'auto',
                })}
            >
                <Box
                    sx={theme => ({
                        display: 'flex',
                        flexDirection: 'row',
                        gap: 1,
                        width: 'fit-content',
                        p: thumbPadding,
                        justifyContent: 'center',
                        [`.${LightboxClasses.ThumbnailContainer}`]: {
                            backgroundColor: theme.palette.background.paper,
                            borderRadius: 2,
                            overflow: 'hidden',
                            boxShadow: '0 4px 8px rgba(0, 0, 0, 0.2)',
                            [`&.${LightboxClasses.SelectedThumbnail}`]: {
                                outline: `3px solid ${isDark ? theme.palette.primary.contrastText : theme.palette.primary.main}`,
                            },
                            [`img`]: {
                                minWidth: 0,
                                display: 'block',
                                maxHeight: thumbHeight,
                            },
                        },
                        ...thumbSx(theme, 30),
                    })}
                >
                    {thumbs.map(t => (
                        <Link
                            id={`t_${t.id}`}
                            to={t.path}
                            key={t.id}
                            className={classnames({
                                [LightboxClasses.ThumbnailContainer]: true,
                                [LightboxClasses.SelectedThumbnail]:
                                    t.id === asset?.id,
                            })}
                        >
                            {t.src ? (
                                <img key={t.id} src={t.src} alt={t.alt} />
                            ) : (
                                <AssetIconThumbnail
                                    mimeType={t.mimeType}
                                    style={{
                                        width: thumbHeight,
                                        height: thumbHeight,
                                    }}
                                />
                            )}
                        </Link>
                    ))}
                </Box>
            </Box>
        </>
    );
}
