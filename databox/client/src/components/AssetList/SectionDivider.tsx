import {CSSProperties, PropsWithChildren} from 'react';
import {Divider, SxProps, Theme, useTheme} from '@mui/material';
import {zIndex} from '../../themes/zIndex';

function applyStyle(
    theme: Theme,
    defaultStyle: CSSProperties,
    style: ((theme: Theme) => CSSProperties) | undefined
): CSSProperties {
    if (style) {
        return {
            ...defaultStyle,
            ...style(theme),
        };
    }

    return defaultStyle;
}

export const sectionDividerClassname = 'section-divider';

type Props = PropsWithChildren<{
    textSx?: (theme: Theme) => CSSProperties;
    rootSx?: (theme: Theme) => CSSProperties;
    dividerSx?: SxProps;
    top: number | undefined;
}>;

export default function SectionDivider({
    top,
    children,
    rootSx,
    textSx,
    dividerSx,
}: Props) {
    const theme = useTheme();

    return (
        <div
            style={applyStyle(
                theme,
                {
                    zIndex: zIndex.sectionDivider,
                    position: undefined !== top ? 'sticky' : undefined,
                    top,
                    backgroundColor: theme.palette.common.white,
                },
                rootSx
            )}
            className={sectionDividerClassname}
        >
            <Divider
                textAlign={'left'}
                sx={dividerSx}
            >
                <div
                    style={applyStyle(
                        theme,
                        {
                            fontSize: 13,
                            paddingTop: theme.spacing(1),
                            paddingBottom: theme.spacing(1),
                        },
                        textSx
                    )}
                >
                    {children}
                </div>
            </Divider>
        </div>
    );
}
