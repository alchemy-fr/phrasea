import React, {FormEvent} from 'react';
import {storePassword} from '../../lib/credential';
import {useTranslation} from 'react-i18next';
import {
    Avatar,
    Box,
    Button,
    Container,
    Paper,
    TextField,
    Typography,
} from '@mui/material';
import {FormRow, RemoteErrors} from '@alchemy/react-form';
import LockIcon from '@mui/icons-material/Lock';

type Props = {
    onAuthorization: () => void;
    authorization?: string;
    securityContainerId: string;
    error?: string;
};

export default function PasswordMethod({
    securityContainerId,
    onAuthorization,
    error,
}: Props) {
    const [password, setPassword] = React.useState('');
    const {t} = useTranslation();
    const onSubmit = (e: FormEvent) => {
        e.preventDefault();

        storePassword(securityContainerId, password);
        onAuthorization();
    };

    const errors: Record<string, string> = {
        invalid_password: t('error.invalid_password', 'Invalid password'),
    };

    const translatedError = error ? (errors[error] ?? error) : undefined;

    return (
        <Container maxWidth={'xs'}>
            <Paper
                sx={{
                    p: 3,
                    mt: 5,
                }}
            >
                <Box
                    sx={{
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        gap: 3,
                    }}
                >
                    <Avatar
                        sx={{
                            bgcolor: 'primary.main',
                            width: 56,
                            height: 56,
                        }}
                    >
                        <LockIcon fontSize={'large'} />
                    </Avatar>
                    <Typography variant="body1">
                        {t(
                            'publication.security.password.intro',
                            `This publication is protected by a password. Please enter the password to access it.`
                        )}
                    </Typography>
                    <form onSubmit={onSubmit} style={{width: '100%'}}>
                        <FormRow>
                            <TextField
                                label={t(
                                    'publication.security.password.form.password.label',
                                    `Password`
                                )}
                                variant="outlined"
                                fullWidth
                                value={password}
                                onChange={e => setPassword(e.target.value)}
                                type="password"
                            />
                        </FormRow>

                        <FormRow>
                            {translatedError && error !== 'missing_password' ? (
                                <RemoteErrors errors={[translatedError]} />
                            ) : null}

                            <Button
                                type="submit"
                                variant="contained"
                                color="primary"
                                fullWidth={true}
                            >
                                {t(
                                    'publication.security.password.form.submit.label',
                                    `Enter`
                                )}
                            </Button>
                        </FormRow>
                    </form>
                </Box>
            </Paper>
        </Container>
    );
}
