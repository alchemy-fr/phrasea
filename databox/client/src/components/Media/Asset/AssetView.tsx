import React, {useMemo} from 'react';
import {Asset} from "../../../types";
import AppDialog from "../../Layout/AppDialog";
import {StackedModalProps} from "../../../hooks/useModalStack";
import {useModalHash} from "../../../hooks/useModalHash";
import FilePlayer from "./FilePlayer";
import useWindowSize from "../../../hooks/useWindowSize";
import {Dimensions} from "./Players";
import {Box} from "@mui/material";

type Props = {
    asset: Asset;
} & StackedModalProps;

const menuWidth = 300;

export default function AssetView({
                                      asset,
                                      open,
                                  }: Props) {
    const {closeModal} = useModalHash();

    const winSize = useWindowSize();

    const maxDimensions = useMemo<Dimensions>(() => {
        return {
            width: winSize.width - menuWidth,
            height: winSize.height - 200,
        };
    }, [winSize]);

    const file = asset.original;

    return <AppDialog
        open={open}
        maxWidth={false}
        title={<>
            Edit asset{' '}
            <b>
                {asset.resolvedTitle}
            </b>
        </>}
        onClose={closeModal}
    >
        <div style={{
            width: '100%',
            display: 'flex',
            flexDirection: 'row',
            justifyContent: 'space-between'
        }}>
            <div>
                {file && <FilePlayer
                    file={file}
                    title={asset.title}
                    maxDimensions={maxDimensions}
                    autoPlayable={false}
                />}
            </div>
            <Box
                sx={theme => ({
                    width: menuWidth,
                    borderLeft: `1px solid ${theme.palette.divider}`
                })}
            >

            </Box>
        </div>
    </AppDialog>
}
