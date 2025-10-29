import {useContext} from 'react';
import {Asset, Collection, Workspace} from '../../../../types';
import {DisplayContext} from '../../DisplayContext';
import assetClasses from '../../../AssetList/classes';
import {useTranslation} from 'react-i18next';
import {WorkspaceChip} from '../../../Ui/WorkspaceChip.tsx';
import {CollectionChip} from '../../../Ui/CollectionChip.tsx';
import CollectionStoryChip from '../../../Ui/CollectionStoryChip.tsx';
import {OnOpen} from '../../../AssetList/types.ts';
import CollectionOrStoryChip from '../../../Ui/CollectionOrStoryChip.tsx';

type Props = {
    asset: Asset;
    workspace?: Workspace;
    collections: Collection[];
    onOpenAsset?: OnOpen;
};

export default function AssetCollectionList({
    asset,
    workspace,
    collections,
    onOpenAsset,
}: Props) {
    const {t} = useTranslation();
    const {
        state: {collectionsLimit, displayCollections},
    } = useContext(DisplayContext)!;

    if (!displayCollections) {
        return <></>;
    }

    const r = (c: Collection) => {
        if (c.storyAsset) {
            return (
                <CollectionStoryChip
                    key={c.id}
                    asset={asset}
                    onOpen={onOpenAsset}
                    storyAsset={c.storyAsset}
                    size={'small'}
                />
            );
        }

        return (
            <CollectionOrStoryChip size={'small'} key={c.id} collection={c} />
        );
    };

    const rest = collections.length - (collectionsLimit - 1);
    const others =
        collectionsLimit > 1
            ? t('asset.collection_list.others', {
                  defaultValue: `+ {{count}} other`,
                  defaultValue_other: `+ {{count}} others`,
                  count: rest,
              })
            : t('asset.collection_list.collections', {
                  defaultValue: `+ {{count}} collection`,
                  defaultValue_other: `+ {{count}} collections`,
                  count: rest,
              });

    const chips =
        collections.length <= collectionsLimit
            ? collections.slice(0, collectionsLimit).map(r)
            : [
                  collections.slice(0, collectionsLimit - 1).map(r),
                  [
                      <CollectionChip
                          key={'o'}
                          size={'small'}
                          label={others}
                          title={collections
                              .slice(collectionsLimit - 1)
                              .map(c => c.titleTranslated)
                              .join('\n')}
                      />,
                  ],
              ].flat();

    return (
        <div className={assetClasses.collectionList}>
            {workspace && (
                <WorkspaceChip size={'small'} workspace={workspace} />
            )}
            {chips}
        </div>
    );
}

export function collectionListSx() {
    return {
        [`.${assetClasses.collectionList}`]: {
            display: 'flex',
            gap: 0.5,
            alignItems: 'center',
            flexWrap: 'wrap',
        },
    };
}
