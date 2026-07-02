import {Chip, ChipProps} from '@mui/material';
import {grey} from '@mui/material/colors';
import {AssetStatus} from '../../types.ts';
import {useAssetStatusLabels} from '../../hooks/useAssetStatusLabels.ts';

type Props = {
    status: AssetStatus;
};

export default function AssetStatusChip({status, ...props}: Props & ChipProps) {
    const labels = useAssetStatusLabels();

    return (
        <Chip
            {...props}
            label={labels[status]}
            sx={() => ({
                ml: 1,
                bgcolor: grey[200],
                color: grey[800],
            })}
        />
    );
}
