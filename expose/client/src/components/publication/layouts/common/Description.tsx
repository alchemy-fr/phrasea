import {Typography} from '@mui/material';

type Props = {
    descriptionHtml?: string;
};

export default function Description({descriptionHtml}: Props) {
    if (!descriptionHtml) {
        return '';
    }

    return (
        <Typography
            variant={'body1'}
            component={'div'}
            sx={theme => ({
                '.attribute-title': {
                    color: theme.palette.primary.main,
                    fontWeight: 700,
                },
                '.attributes': {
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 1,
                },
            })}
        >
            <div
                dangerouslySetInnerHTML={{
                    __html: descriptionHtml,
                }}
            />
        </Typography>
    );
}
