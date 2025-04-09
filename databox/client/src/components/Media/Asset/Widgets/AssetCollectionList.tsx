import {useContext} from 'react';
import {Collection, Workspace} from '../../../../types';
import {DisplayContext} from '../../DisplayContext';
import {CollectionChip, WorkspaceChip} from '../../../Ui/Chips';
import assetClasses from '../../../AssetList/classes';
import {useTranslation} from 'react-i18next';

type Props = {
    workspace?: Workspace;
    collections: Collection[];
};

export default function AssetCollectionList({workspace, collections}: Props) {
    const {t} = useTranslation();
    const {collectionsLimit, displayCollections} = useContext(DisplayContext)!;

    if (!displayCollections) {
        return <></>;
    }

    const r = (c: Collection) => (
        <CollectionChip size={'small'} key={c.id} label={c.titleTranslated} />
    );

    const rest = collections.length - (collectionsLimit - 1);
    const others =
        collectionsLimit > 1
            ? t('asset.collection_list.others', `+ {{count}} others`, {
                  count: rest,
              })
            : t('asset.collection_list.collections', `+ {{count}} collection`, {
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
                <WorkspaceChip
                    size={'small'}
                    label={workspace.nameTranslated}
                />
            )}
            {chips}
        </div>
    );
}

export function collectionListSx() {
    return {
        [`.${assetClasses.collectionList}`]: {
            'px': 1,
            'display': 'flex',
            'alignItems': 'center',
            'flexWrap': 'wrap',
            '.MuiChip-root': {
                my: 0.5,
            },
            '.MuiChip-root+.MuiChip-root': {
                ml: 0.5,
            },
        },
    };
}
