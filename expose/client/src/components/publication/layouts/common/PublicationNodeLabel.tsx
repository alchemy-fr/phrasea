import {Publication} from '../../../../types.ts';
import {ListItemText} from '@mui/material';
import {getTranslatedTitle} from '../../../../i18n.ts';
import {RenderNodeProps} from '@alchemy/phrasea-framework';

export default function PublicationNodeLabel({
    node,
}: RenderNodeProps<Publication>) {
    return (
        <>
            <ListItemText primary={getTranslatedTitle(node.data)} />
        </>
    );
}
