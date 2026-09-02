import {Box, useTheme} from '@mui/material';
import {AppLogo} from '@alchemy/phrasea-framework';
import {config} from '../../init.ts';
import {useTranslation} from 'react-i18next';

type Props = {
    logo: string | null | undefined;
};

export default function ShareLogo({logo}: Props) {
    const {t} = useTranslation();
    const theme = useTheme();
    const width = theme.breakpoints.values.md;

    return (
        <Box
            sx={{
                maxWidth: width,
                margin: '0 auto',
                py: 2,
                display: 'flex',
                justifyContent: 'center',
            }}
        >
            {logo ? (
                <img
                    src={logo}
                    alt={''}
                    style={{
                        maxHeight: 60,
                        maxWidth: 300,
                    }}
                />
            ) : (
                <AppLogo
                    appTitle={t('common.databox', `Databox`)}
                    config={config}
                />
            )}
        </Box>
    );
}
