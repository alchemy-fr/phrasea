import {Asset} from '../../../types.ts';
import {Typography} from '@mui/material';

type Props = {
    asset: Asset;
};

export default function AssetLegend({asset}: Props) {
    if (!asset.title && !asset.description) {
        return null;
    }

    return (
        <div>
            {asset.title ? (
                <Typography
                    variant={'h1'}
                    sx={{
                        mb: 2,
                    }}
                >
                    {asset.title}
                </Typography>
            ) : null}
            {asset.description ? (
                <Typography variant={'body1'}>{asset.description}</Typography>
            ) : null}
        </div>
    );
}
