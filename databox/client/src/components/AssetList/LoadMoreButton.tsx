import {Box} from '@mui/material';
import {LoadingButton} from '@mui/lab';
import ArrowCircleDownIcon from '@mui/icons-material/ArrowCircleDown';
import {VoidFunction} from '../../lib/utils';
import {useTranslation} from 'react-i18next';
import {AssetOrAssetContainer} from "../../types";
import React from "react";
import assetClasses from "./classes";
import {useInfiniteScroll} from "../../hooks/useInfiniteScroll.ts";

type Props<Item extends AssetOrAssetContainer> = {
    onClick: VoidFunction;
    pages: Item[][];
};

export default function LoadMoreButton<Item extends AssetOrAssetContainer>({onClick, pages}: Props<Item>) {
    const {t} = useTranslation();
    const [loading, setLoading] = React.useState(false);
    const ref = React.useRef<HTMLDivElement>();
    const loadingRef = React.useRef(false);

    const doLoad = React.useCallback(() => {
        if (loadingRef.current) {
            return;
        }
        loadingRef.current = true;
        setLoading(true);
        onClick();
    }, [onClick]);

    React.useEffect(() => {
        setLoading(false);
        loadingRef.current = false;
    }, [pages]);

    useInfiniteScroll({
        onLoad: doLoad,
        node: ref.current?.closest(`.${assetClasses.scrollable}`)
    });

    return (
        <Box
            ref={ref}
            sx={{
                textAlign: 'center',
                my: 4,
            }}
        >
            <LoadingButton
                loading={loading}
                startIcon={<ArrowCircleDownIcon />}
                onClick={doLoad}
                variant="contained"
                color="secondary"
            >
                {loading
                    ? t('load_more.button.loading', 'Loading...')
                    : t('load_more.button.loading', 'Load more')}
            </LoadingButton>
        </Box>
    );
}
