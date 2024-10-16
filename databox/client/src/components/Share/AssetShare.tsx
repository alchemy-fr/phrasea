import {Asset} from '../../types.ts';
import FilePlayer from '../Media/Asset/FilePlayer.tsx';
import {Box, Paper, Typography, useTheme} from '@mui/material';
import Attributes, {
    attributesSx,
} from '../Media/Asset/Attribute/Attributes.tsx';
import DisplayProvider from '../Media/DisplayProvider.tsx';

type Props = {
    asset: Asset;
};

export default function AssetShare({asset}: Props) {
    const rendition = asset.preview || asset.thumbnail || asset.original;
    const theme = useTheme();

    const width = theme.breakpoints.values.md;

    if (!rendition?.file) {
        return null;
    }

    return (
        <>
            <DisplayProvider thumbSize={width}>
                <Paper
                    elevation={1}
                    sx={{
                        maxWidth: width,
                        margin: '0 auto',
                    }}
                >
                    <Typography
                        variant="h1"
                        sx={{
                            textAlign: 'center',
                            p: 2,
                        }}
                    >
                        {asset.resolvedTitle}
                    </Typography>
                    <div
                        style={{
                            position: 'relative',
                            width: 'fit-content',
                            margin: '0 auto',
                        }}
                    >
                        <FilePlayer
                            file={rendition.file!}
                            title={asset.resolvedTitle}
                            autoPlayable={false}
                            controls={true}
                        />
                    </div>
                    <Box
                        sx={{
                            ...attributesSx(),
                            p: 3,
                        }}
                    >
                        <Attributes asset={asset} displayControls={true} />
                    </Box>
                </Paper>
            </DisplayProvider>
        </>
    );
}
