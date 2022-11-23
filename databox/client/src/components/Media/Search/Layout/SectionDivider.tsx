import React, {CSSProperties, PropsWithChildren} from 'react';
import {Theme, useTheme} from "@mui/material";
import {zIndex} from "../../../../themes/zIndex";

type Props = PropsWithChildren<{
    textStyle?: (theme: Theme) => CSSProperties;
    rootStyle?: (theme: Theme) => CSSProperties;
}>;

function applyStyle(theme: Theme, defaultStyle: CSSProperties, style: ((theme: Theme) => CSSProperties) | undefined): CSSProperties {
    if (style) {
        return {
            ...defaultStyle,
            ...style(theme),
        }
    }

    return defaultStyle;
}

export default function SectionDivider({
                                           children,
                                           rootStyle,
                                           textStyle,
                                       }: Props) {

    const theme = useTheme();
    const bg = theme.palette.common.white;
    const spacing1 = theme.spacing(1);
    const spacing2 = theme.spacing(2);

    return <div
        style={applyStyle(theme, {
            zIndex: zIndex.sectionDivider,
            width: '100%',
            position: 'sticky',
            top: 55,
            backgroundColor: bg,
            overflow: 'visible',
        }, rootStyle)}
    >
        <div
            style={{
                borderBottom: `1px solid ${theme.palette.divider}`,
                color: theme.palette.secondary.main,
                margin: `${spacing2} 0`,
                position: 'relative',
            }}
       >
            <div
                style={applyStyle(theme, {
                    backgroundColor: bg,
                    top: `-9px`,
                    padding: `0 ${spacing1}`,
                    marginLeft: `${spacing2}`,
                    fontSize: 13,
                    position: 'absolute'
                }, textStyle)}
            >
                {children}
            </div>
        </div>
    </div>
}
