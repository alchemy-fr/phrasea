import React from 'react';
import {Chip, ChipProps} from "@mui/material";
import {invertColor} from "../../lib/colors";

type Props = {
    name: string;
    color: string | null;
    title?: string;
    size?: ChipProps['size'];
};

export const tagClassName = 'pTag';

export default function TagNode({
    color,
    name,
    title,
    size,
}: Props) {
    const c: string = color ?? '#CCC';

    return <Chip
        style={{
            backgroundColor: c,
            color: invertColor(c, true),
        }}
        className={tagClassName}
        title={title}
        label={name}
        size={size}
    />
}
