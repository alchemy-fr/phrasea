import React, {CSSProperties, PropsWithChildren} from 'react';
import {Divider, Theme, useTheme} from "@mui/material";
import {zIndex} from "../../../../themes/zIndex";
import {SxProps} from "@mui/system";

function applyStyle(
    theme: Theme,
    defaultStyle: CSSProperties,
    style: ((theme: Theme) => CSSProperties) | undefined
): CSSProperties {
    if (style) {
        return {
            ...defaultStyle,
            ...style(theme),
        }
    }

    return defaultStyle;
}

export const sectionDividerClassname = 'section-divider';

type Props = PropsWithChildren<{
    textStyle?: (theme: Theme) => CSSProperties;
    rootStyle?: (theme: Theme) => CSSProperties;
    dividerSx?: SxProps;
}>;

export default function SectionDivider({
    children,
    rootStyle,
    textStyle,
    dividerSx,
}: Props) {
    const theme = useTheme();

    return <div
        style={applyStyle(theme, {
            zIndex: zIndex.sectionDivider,
            position: 'sticky',
            top: 55,
            backgroundColor: theme.palette.common.white,
        }, rootStyle)}
        className={sectionDividerClassname}
    >
        <Divider
            textAlign={'left'}
            sx={dividerSx}
        >
            <div
                style={applyStyle(theme, {
                    fontSize: 13,
                    paddingTop: theme.spacing(1),
                    paddingBottom: theme.spacing(1),
                }, textStyle)}
            >
                {children}
            </div>
        </Divider>
    </div>
}
