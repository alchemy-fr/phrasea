import {
    NavigationNode,
    NavigationRootNode,
    NavigationTree,
} from '@alchemy/phrasea-framework';
import {Publication} from '../../../../types.ts';
import {useNavigateToPublication} from '../../../../hooks/useNavigateToPublication.ts';
import PublicationTreeItem from './PublicationTreeItem.tsx';

type Props = {
    publication: Publication;
};

export default function PublicationsTree({publication}: Props) {
    const navigateToPublication = useNavigateToPublication();
    const pubToNode = (pub: Publication): NavigationNode<Publication> => ({
        id: pub.id,
        data: pub,
        children: pub.children?.map(c => pubToNode(c)) ?? [],
        childrenLoaded: true,
        hasChildren: false,
    });

    const rootItem: NavigationRootNode<Publication> = {
        children: [pubToNode(publication)],
        hasChildren: true,
        childrenLoaded: true,
    };

    return (
        <>
            {publication.parent ? (
                <PublicationTreeItem
                    navigateToPublication={navigateToPublication}
                    data={publication.parent}
                    level={0}
                />
            ) : null}
            <NavigationTree
                rootItem={rootItem}
                renderItem={({level, data, ...rest}) => {
                    return (
                        <PublicationTreeItem
                            navigateToPublication={navigateToPublication}
                            level={level + 1}
                            {...rest}
                            data={data}
                            item={{
                                ...rest.item,
                                selected: data === publication,
                            }}
                        />
                    );
                }}
            />
        </>
    );
}
