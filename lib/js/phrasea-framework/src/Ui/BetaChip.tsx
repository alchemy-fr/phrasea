import {resolveSx} from '@alchemy/core';
import {Chip, ChipProps, Tooltip} from '@mui/material';
import {useTranslation} from 'react-i18next';

type Props = {
    sx?: ChipProps['sx'];
};

export default function BetaChip({sx}: Props) {
    const {t} = useTranslation();
    return (
        <Tooltip
            title={t(
                'lib.ui.beta.tooltip',
                'This feature is in beta and may change without notice'
            )}
        >
            <Chip
                sx={theme => ({
                    textTransform: 'uppercase',
                    fontStyle: 'normal',
                    fontWeight: 400,
                    fontSize: '0.7rem',
                    ...resolveSx(sx, theme),
                })}
                label={t('lib.ui.beta.label', 'Beta')}
            />
        </Tooltip>
    );
}
