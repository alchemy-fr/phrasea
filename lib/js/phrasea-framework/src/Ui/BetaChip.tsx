import {resolveSx} from '@alchemy/core';
import {Chip, ChipProps, Tooltip} from '@mui/material';
import {useTranslation} from 'react-i18next';

type Props = {
    sx?: ChipProps['sx'];
    size?: ChipProps['size'];
};

export default function BetaChip({sx, size}: Props) {
    const {t} = useTranslation();
    return (
        <Tooltip
            title={t(
                'lib.ui.beta.tooltip',
                'This feature is in beta and may change without notice'
            )}
        >
            <Chip
                size={size}
                sx={theme => ({
                    textTransform: 'uppercase',
                    fontStyle: 'normal',
                    fontWeight: 400,
                    fontSize: size === 'small' ? '0.5rem' :'0.7rem',
                    ...resolveSx(sx, theme),
                })}
                label={t('lib.ui.beta.label', 'Beta')}
            />
        </Tooltip>
    );
}
