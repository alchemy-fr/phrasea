import {Box} from '@mui/material';
import {Asset, Thumb} from '../../../types.ts';
import {getPath, Link, useNavigate} from '@alchemy/navigation';
import {useEffect} from 'react';
import {routes} from '../../../routes.ts';
import {FilePlayer} from '@alchemy/phrasea-framework';

type Props = {
    thumbs: Thumb[];
    asset: Asset;
    publicationId: string;
};

export default function Lightbox({publicationId, thumbs, asset}: Props) {
    const navigate = useNavigate();

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                navigate(
                    getPath(routes.publication, {
                        id: publicationId,
                    })
                );
            } else if (
                event.key === 'ArrowRight' ||
                event.key === 'ArrowLeft'
            ) {
                const currentIndex = thumbs.findIndex(t => t.id === asset.id);
                console.log('currentIndex', currentIndex, thumbs);
                let newIndex = currentIndex;

                if (event.key === 'ArrowRight') {
                    newIndex = (currentIndex + 1) % thumbs.length;
                } else if (event.key === 'ArrowLeft') {
                    newIndex =
                        (currentIndex - 1 + thumbs.length) % thumbs.length;
                }

                navigate(
                    getPath(routes.publication.routes.asset, {
                        id: publicationId,
                        assetId: thumbs[newIndex].id,
                    })
                );
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [thumbs, navigate, publicationId, asset]);

    return (
        <div
            style={{
                position: 'absolute',
                top: 0,
                left: 0,
                bottom: 0,
                right: 0,
                backgroundColor: 'rgba(0, 0, 0, 0.80)',
                zIndex: 1300,
            }}
        >
            <Box
                sx={{
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'center',
                    alignItems: 'center',
                    height: '100vh',
                }}
            >
                <Box
                    style={{
                        position: 'relative',
                        flexGrow: 1,
                        flexShrink: 1,
                        width: '100%',
                    }}
                >
                    <div
                        style={{
                            position: 'absolute',
                            top: 0,
                            right: 0,
                            bottom: 0,
                            left: 0,
                            margin: 'auto',
                            maxWidth: '100%',
                            maxHeight: '100%',
                            display: 'flex',
                            justifyContent: 'center',
                            alignItems: 'center',
                        }}
                    >
                        <FilePlayer
                            file={{
                                id: asset.id,
                                name: asset.title ?? 'Asset',
                                type: asset.mimeType,
                                url: asset.previewUrl,
                            }}
                            title={asset.title ?? 'Asset'}
                        />
                    </div>
                </Box>
                <Box
                    sx={theme => ({
                        display: 'flex',
                        flexDirection: 'row',
                        gap: 1,
                        p: 1,
                        justifyContent: 'center',
                        img: {
                            'borderRadius': 2,
                            'boxShadow': '0 4px 8px rgba(0, 0, 0, 0.2)',
                            'maxHeight': 80,
                            '&.selected': {
                                outline: `3px solid ${theme.palette.primary.contrastText}`,
                            },
                        },
                    })}
                >
                    {thumbs.map(t => (
                        <Link to={t.path} key={t.id}>
                            <img
                                key={t.id}
                                src={t.src}
                                alt={t.alt}
                                className={t.id === asset.id ? 'selected' : ''}
                            />
                        </Link>
                    ))}
                </Box>
            </Box>
        </div>
    );
}
