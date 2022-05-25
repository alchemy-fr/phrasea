import React, {useCallback, useState} from 'react';
import {Asset} from "../../../types";
import {Popper} from "@mui/material";
import FilePlayer from "./FilePlayer";

type Props = {
    anchorEl: HTMLElement | undefined;
    asset: Asset | undefined;
};

const size = 400;

export default function PreviewPopover({
                                           asset,
                                           anchorEl,
                                       }: Props) {
    const [anchor, setAnchor] = useState<HTMLElement>();

    const onLoad = useCallback(() => {
        setAnchor(anchorEl);
    }, [anchorEl]);

    return <Popper
        keepMounted={true}
        open={Boolean(anchor && asset)}
        placement="bottom"
        anchorEl={anchor || null}
        sx={{
            pointerEvents: 'none',
            'img': {
                maxWidth: size,
                maxHeight: size,
            },
            zIndex: 3,
        }}
        modifiers={[
            {
                name: 'flip',
                enabled: true,
                options: {
                    altBoundary: true,
                    rootBoundary: 'document',
                    padding: 8,
                },
            },
            {
                name: 'preventOverflow',
                enabled: true,
                options: {
                    altAxis: true,
                    altBoundary: true,
                    tether: true,
                    rootBoundary: 'document',
                    padding: 8,
                },
            },
        ]}
    >
        {asset && <>
            <FilePlayer
                file={asset.preview!}
                size={size}
                title={asset.resolvedTitle}
                onLoad={onLoad}
            />

        </>}
    </Popper>
}
