import {Box} from '@mui/material';

type Props = {
    descriptionHtml?: string;
};

export default function Description({descriptionHtml}: Props) {
    if (!descriptionHtml) {
        return '';
    }

    return (
        <Box
            sx={{
                mt: 2,
            }}
        >
            <div
                dangerouslySetInnerHTML={{
                    __html: descriptionHtml,
                }}
            />
        </Box>
    );
}
