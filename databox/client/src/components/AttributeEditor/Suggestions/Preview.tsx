import {SuggestionTabProps} from '../types.ts';
import React from 'react';
import {useTranslation} from 'react-i18next';
import {Asset} from '../../../types.ts';
import FilePlayer from '../../Media/Asset/FilePlayer.tsx';
import {Box, IconButton, Typography} from '@mui/material';
import {useElementResize} from '@alchemy/react-hooks/src/useElementResize';
import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';
import KeyboardArrowRightIcon from '@mui/icons-material/KeyboardArrowRight';

type Props<T> = {} & SuggestionTabProps<T>;

export default function Preview<T>({
    subSelection,
    defaultPanelWidth,
}: Props<T>) {
    const {t} = useTranslation();
    const [asset, setAsset] = React.useState<Asset | undefined>(
        subSelection[0]
    );
    const containerRef = React.useRef<HTMLDivElement | null>(null);
    const size = useElementResize(containerRef.current);
    const finalWidth = size ? size.width : defaultPanelWidth;
    const index: number = asset
        ? subSelection.findIndex(i => i.id === asset.id)
        : -1;

    const goTo = React.useCallback(
        (offset: number) => {
            setAsset(p => {
                if (!p) {
                    return subSelection[0];
                }

                const index = subSelection.findIndex(i => i.id === p.id);

                return subSelection[
                    (index + subSelection.length + offset) % subSelection.length
                ];
            });
        },
        [subSelection]
    );

    React.useEffect(() => {
        setAsset(subSelection[0]);
    }, [subSelection]);

    return (
        <div ref={containerRef}>
            {asset ? (
                <>
                    <Box
                        sx={{
                            bgcolor: 'background.default',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        {asset.preview ? (
                            <FilePlayer
                                key={asset.id}
                                trackingId={asset.resolvedTrackingId}
                                file={asset.preview!.file!}
                                title={asset.resolvedTitle}
                                dimensions={{
                                    width: finalWidth,
                                }}
                                controls={true}
                                autoPlayable={true}
                            />
                        ) : (
                            ''
                        )}
                    </Box>
                    <Box
                        sx={{
                            p: 2,
                            mb: 5,
                        }}
                    >
                        <Typography
                            variant={'h5'}
                            sx={{
                                mb: 2,
                            }}
                        >
                            {asset.resolvedTitle}
                        </Typography>
                    </Box>
                    {index >= 0 ? (
                        <Box
                            sx={theme => ({
                                'p': 1,
                                'position': 'absolute',
                                'zIndex': theme.zIndex.speedDial,
                                'bottom': 0,
                                'width': '100%',
                                'display': 'flex',
                                'flexDirection': 'row',
                                'alignItems': 'center',
                                'justifyContent': 'center',
                                '> *': {
                                    mx: 1,
                                },
                            })}
                        >
                            <IconButton onClick={() => goTo(-1)}>
                                <KeyboardArrowLeftIcon />
                            </IconButton>
                            <div>{`${index + 1} / ${subSelection.length}`}</div>
                            <IconButton onClick={() => goTo(1)}>
                                <KeyboardArrowRightIcon />
                            </IconButton>
                        </Box>
                    ) : (
                        ''
                    )}
                </>
            ) : (
                <>
                    {t(
                        'attribute.editor.tabs.preview.no_asset',
                        'No asset selected'
                    )}
                </>
            )}
        </div>
    );
}
