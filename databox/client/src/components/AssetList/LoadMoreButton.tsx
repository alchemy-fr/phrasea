import {Box} from "@mui/material";
import {LoadingButton} from "@mui/lab";
import ArrowCircleDownIcon from "@mui/icons-material/ArrowCircleDown";
import {VoidFunction} from "../../lib/utils.ts";
import {useTranslation} from 'react-i18next';

type Props = {
    loading: boolean;
    onClick: VoidFunction;
};

export default function LoadMoreButton({
    onClick,
    loading,
}: Props) {
    const {t} = useTranslation();

    return <Box
                sx={{
                    textAlign: 'center',
                    my: 4,
                }}
            >
                <LoadingButton
                    loading={loading}
                    startIcon={<ArrowCircleDownIcon />}
                    onClick={onClick}
                    variant="contained"
                    color="secondary"
                >
                    {loading
                        ? t('load_more.button.loading', 'Loading...')
                        : t('load_more.button.loading', 'Load more')}
                </LoadingButton>
            </Box>
}
