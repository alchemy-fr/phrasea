import {ReactNode} from 'react';
import {Box, Stack, Typography} from '@mui/material';
import {Asset} from '../../../../types.ts';
import AssetThumb, {thumbSx} from '../AssetThumb.tsx';

type Props = {
    asset: Asset;
    title?: string;
    subtitle?: ReactNode;
    selected?: boolean;
    onClick?: () => void;
    leading?: ReactNode;
};

/**
 * A duplicate asset displayed with its thumbnail: shared by the duplicates
 * list of the analysis report and the merge/add-as-version dialogs.
 */
export default function DuplicateAssetRow({
    asset,
    title,
    subtitle,
    selected,
    onClick,
    leading,
}: Props) {
    const displayTitle = title ?? asset.name ?? asset.id;

    return (
        <Box
            sx={theme => ({
                display: 'flex',
                alignItems: 'center',
                gap: 1.5,
                p: 1,
                borderRadius: 1,
                border: `1px solid ${
                    selected
                        ? theme.palette.primary.main
                        : theme.palette.divider
                }`,
                mb: 1,
                cursor: onClick ? 'pointer' : undefined,
                ...thumbSx(48, theme),
            })}
            onClick={onClick}
        >
            {leading}
            <AssetThumb asset={asset} noStoryCarousel={true} />
            <Stack sx={{minWidth: 0}}>
                <Typography noWrap sx={{fontWeight: 500}} title={displayTitle}>
                    {displayTitle}
                </Typography>
                {subtitle ? (
                    <Typography
                        variant={'body2'}
                        color={'text.secondary'}
                        noWrap
                    >
                        {subtitle}
                    </Typography>
                ) : null}
            </Stack>
        </Box>
    );
}
