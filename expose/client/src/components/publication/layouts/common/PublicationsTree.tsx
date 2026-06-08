import {
    getAllTreeNodeIds,
    TreeNode,
    TreeView,
    useTreeState,
} from '@alchemy/phrasea-framework';
import {Publication} from '../../../../types.ts';
import {useNavigateToPublication} from '../../../../hooks/useNavigateToPublication.ts';
import PublicationNodeLabel from './PublicationNodeLabel.tsx';
import {useMemo} from 'react';

type Props = {
    publication: Publication;
};

export default function PublicationsTree({publication}: Props) {
    const navigateToPublication = useNavigateToPublication();
    const pubToNode = (pub: Publication): TreeNode<Publication> => ({
        id: pub.id,
        data: pub,
        children: pub.children?.map(c => pubToNode(c)) ?? [],
        hasChildren: Boolean(pub.children?.length > 0),
    });

    const nodes = useMemo(() => {
        let initialNodes: TreeNode<Publication>[] = [pubToNode(publication)];
        if (publication.parent) {
            initialNodes = [
                {
                    id: publication.parent.id,
                    data: publication.parent,
                    children: initialNodes,
                    hasChildren: initialNodes.length > 0,
                },
            ];
        }
        return initialNodes;
    }, [publication]);

    const allNodes = useMemo(() => getAllTreeNodeIds(nodes), [nodes]);

    const treeStateProps = useTreeState({
        defaultSelectedNodes: allNodes,
        defaultExpandedNodes: [publication.id],
    });

    return (
        <>
            <TreeView
                {...treeStateProps}
                key={publication.id}
                required={true}
                onToggleSelect={(node, selected) => {
                    if (selected) {
                        navigateToPublication(node.data);
                    }
                }}
                nodes={nodes}
                renderNodeLabel={props => {
                    return <PublicationNodeLabel {...props} />;
                }}
            />
        </>
    );
}
