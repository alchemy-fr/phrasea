import {Asset} from "../../types.ts";
import FilePlayer from "../Media/Asset/FilePlayer.tsx";
import {FlexRow} from "@alchemy/phrasea-ui";
import {Box, Typography} from "@mui/material";
import Attributes, {attributesSx} from "../Media/Asset/Attribute/Attributes.tsx";

type Props = {
    asset: Asset;
};

export default function AssetShare({
    asset,
}: Props) {
    const rendition = asset.preview || asset.thumbnail || asset.original;

    if (!rendition?.file) {
        return null;
    }

    return <FlexRow style={{
        justifyContent: 'center',
    }}>
        <div style={{
            width: 'auto'
        }}>
            <Typography variant="h1" sx={{
                textAlign: 'center',
                my: 2,
            }}>
                {asset.resolvedTitle}
            </Typography>
            <FilePlayer
                file={rendition.file!}
                title={asset.resolvedTitle}
            />

            <Box sx={{
                ...attributesSx(),
                mt: 2,
            }}>
                <Attributes
                    asset={asset}
                    displayControls={true}
                />
            </Box>
        </div>
    </FlexRow>
}
