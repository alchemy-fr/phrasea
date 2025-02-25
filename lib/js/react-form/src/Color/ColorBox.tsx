import React, {PropsWithChildren} from 'react';

type ColorBoxProps = PropsWithChildren<{
    color: string;
    width?: number;
    height?: number;
    borderWidth?: number;
}> &
    React.HTMLProps<HTMLDivElement>;

export function ColorBox({
    color,
    width = 30,
    height = 22,
    borderWidth = 2,
    children,
    style,
    ...divProps
}: ColorBoxProps) {
    return (
        <div
            style={{
                width,
                height,
                backgroundColor: color,
                border: `${borderWidth}px solid #000`,
                ...(style || {}),
            }}
            {...divProps}
        >
            {children}
        </div>
    );
}
