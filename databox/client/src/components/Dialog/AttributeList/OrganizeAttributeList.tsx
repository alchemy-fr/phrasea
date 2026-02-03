import {AttributeList} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {useAttributeListStore} from '../../../store/attributeListStore.ts';
import React from 'react';
import AttributeDefinitionTransferList from './Transfer/AttributeDefinitionTransferList.tsx';
import {
    useIndexById,
    useAttributeDefinitionStore,
} from '../../../store/attributeDefinitionStore.ts';
import {useTranslation} from 'react-i18next';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {Button} from '@mui/material';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';

type Props = {
    id: string;
    data: AttributeList;
} & DialogTabProps;

export default function OrganizeAttributeList({
    data,
    onClose,
    minHeight,
}: Props) {
    const {t} = useTranslation();

    const load = useAttributeDefinitionStore(state => state.load);

    React.useEffect(() => {
        load(t);
    }, [load]);

    const {definitions, loaded} = useAttributeDefinitionStore(state => ({
        definitions: state.definitions,
        loaded: state.loaded,
    }));
    const definitionsIndex = useIndexById();

    const {sortList, removeFromList, addToList} = useAttributeListStore(
        state => ({
            sortList: state.sortList,
            removeFromList: state.removeFromList,
            addToList: state.addToList,
        })
    );

    if (!loaded || !data.items) {
        return <FullPageLoader />;
    }

    return (
        <>
            <DialogContent
                sx={{
                    minHeight,
                }}
            >
                <AttributeDefinitionTransferList
                    listId={data.id}
                    definitions={definitions}
                    definitionsIndex={definitionsIndex}
                    list={data.items!}
                    onSort={items => {
                        sortList(data.id, items);
                    }}
                    onAdd={items => {
                        addToList(data.id, items);
                    }}
                    onRemove={items => {
                        removeFromList(data.id, items);
                    }}
                />
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>{t('dialog.close', 'Close')}</Button>
            </DialogActions>
        </>
    );
}
