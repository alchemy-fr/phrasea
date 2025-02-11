import {useTranslation} from 'react-i18next';
import {Alert, AlertTitle, Box, Typography} from '@mui/material';
import type {LastErrors} from '../../../types.ts';

type Props<T extends {lastErrors?: LastErrors}> = {
    data: T;
};

export default function LastErrorsList<T extends {lastErrors?: LastErrors}>({
    data,
}: Props<T>) {
    const {t} = useTranslation();
    if (!data.lastErrors?.length) {
        return null;
    }

    return (
        <Box sx={{mb: 2}}>
            <Typography variant={'body1'} color={'error'}>
                {t('form.integration.errors.label', 'Last errors:')}
            </Typography>
            {data.lastErrors.map((e, i) => (
                <Alert severity="error" key={i}>
                    <AlertTitle>{e.message}</AlertTitle>
                    {e.date}
                </Alert>
            ))}
        </Box>
    );
}
