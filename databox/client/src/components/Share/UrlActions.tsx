import {FlexRow} from '@alchemy/phrasea-ui';
import {IconButton} from '@mui/material';
import OpenInNewIcon from '@mui/icons-material/OpenInNew';
import {Share} from '../../types.ts';

type Props = {
    url: string;
};

export function UrlActions({url}: Props) {
    return (
        <FlexRow>
            <IconButton href={url} target={'_blank'}>
                <OpenInNewIcon />
            </IconButton>
        </FlexRow>
    );
}

export function getShareTitle(share: Share): string {
    return share.asset?.resolvedTitle ?? 'Databox';
}
