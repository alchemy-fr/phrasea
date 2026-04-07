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
                '.field-title': {
                    color: theme.palette.primary.main,
                    fontWeight: 700,
                },
                '.field-value': {
                    color: theme.palette.text.primary,
                    marginInlineStart: 0,
                    mb: 1,
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
