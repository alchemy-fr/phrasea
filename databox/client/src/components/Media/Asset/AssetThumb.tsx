import React, {MouseEvent} from 'react';
import {Asset} from "../../../types";
import {Box} from "@mui/material";
import {Theme} from "@mui/material/styles";

type Props = {
    selected?: boolean;
    displayAttributes: boolean;
    onClick?: (id: string, e: MouseEvent) => void;
} & Asset;

const size = 150;

const assetSx = (theme: Theme) => ({
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: size,
    height: size,
    backgroundColor: theme.palette.grey[100],
    'img': {
        maxWidth: '100%',
        maxHeight: '100%',
    }
});

export default function AssetThumb({

                                      id,
                                      resolvedTitle,
                                      titleHighlight,
                                      description,
                                      workspace,
                                      tags,
                                      original,
                                      thumbnail,
                                      thumbnailActive,
                                      privacy,
                                      selected,
                                      collections,
                                      capabilities,
                                  }: Props) {


    return <Box sx={assetSx}>
        {thumbnail && <img src={thumbnail.url} alt={resolvedTitle}/>}
        {thumbnailActive && <img
            src={thumbnailActive.url}
            alt={resolvedTitle}
            className={'ta'}
        />}
    </Box>
}
