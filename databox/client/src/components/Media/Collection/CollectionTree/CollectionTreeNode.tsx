import {RenderNodeProps} from '@alchemy/phrasea-framework';
import {WorkspaceOrCollectionTreeItem} from './types.ts';
import {ListItemText} from '@mui/material';

type Props = RenderNodeProps<WorkspaceOrCollectionTreeItem>;

export default function CollectionTreeNode({node}: Props) {
    return (
        <>
            <ListItemText primary={node.data.label} />
        </>
    );
}
