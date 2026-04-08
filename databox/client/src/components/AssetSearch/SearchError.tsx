import {Trans} from 'react-i18next';
import CancelPresentationIcon from '@mui/icons-material/CancelPresentation';
import {Avatar, Container, Typography} from '@mui/material';
import {FlexRow} from '@alchemy/phrasea-ui';

type Props = {
    error: string;
};

export default function SearchError({error}: Props) {
    return (
        <Container
            sx={{
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                color: 'error.main',
            }}
        >
            <div>
                <FlexRow gap={2}>
                    <Avatar
                        sx={{
                            bgcolor: 'error.main',
                        }}
                    >
                        <CancelPresentationIcon />
                    </Avatar>
                    <Typography variant={'h2'}>
                        <Trans i18nKey={'search.error'}>
                            Oops, an error occurred:
                        </Trans>
                    </Typography>
                </FlexRow>
                <pre
                    style={{
                        whiteSpace: 'pre-wrap',
                    }}
                >
                    {error}
                </pre>
            </div>
        </Container>
    );
}
