import React from 'react';
import {Box, Stack, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {colors} from './colors.ts';

type Props = {
    onTextColorChange?: (color: string) => void;
    onBackgroundColorChange?: (color: string) => void;
};

enum Classes {
    ColorContainer = 'colors-container',
    Color = 'color',
}

export default function ColorPalette({
    onTextColorChange,
    onBackgroundColorChange,
}: Props) {
    const {t} = useTranslation();
    const size = 25;

    return (
        <Stack
            spacing={2}
            sx={theme => ({
                p: 2,
                [`.${Classes.ColorContainer}`]: {
                    display: 'grid',
                    gridTemplateColumns: `repeat(10, ${size}px)`,
                    gridTemplateRows: `repeat(8, ${size}px)`,
                    gap: 0.5,
                },
                [`.${Classes.Color}`]: {
                    'borderRadius': 1,
                    'width': size,
                    'height': size,
                    'border': `1px solid  ${theme.palette.divider}`,
                    'cursor': 'pointer',
                    ':hover': {
                        border: `2px solid ${theme.palette.primary.main}`,
                    },
                },
            })}
        >
            <div>
                <Typography>
                    {t('editor.menu.colors.textColor', 'Text Color')}
                </Typography>
                <div className={Classes.ColorContainer}>
                    {colors.map(color => (
                        <div
                            key={color.rgb}
                            onClick={() => onTextColorChange?.(color.rgb)}
                            className={Classes.Color}
                            title={color.label}
                            style={{
                                backgroundColor: color.rgb,
                            }}
                        />
                    ))}
                </div>
            </div>
            {onBackgroundColorChange && (
                <Box mt={2}>
                    <Typography>
                        {t(
                            'editor.menu.colors.backgroundColor',
                            'Background Color'
                        )}
                    </Typography>
                    <div className={Classes.ColorContainer}>
                        {colors.map(color => (
                            <div
                                key={color.rgb}
                                onClick={() =>
                                    onBackgroundColorChange?.(color.rgb)
                                }
                                title={color.label}
                                className={Classes.Color}
                                style={{
                                    backgroundColor: color.rgb,
                                }}
                            />
                        ))}
                    </div>
                </Box>
            )}
        </Stack>
    );
}
