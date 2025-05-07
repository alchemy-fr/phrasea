import {AttributeList} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {useAttributeListStore} from "../../../store/attributeListStore.ts";
import React from "react";
import AttributeDefinitionTransferList from "./AttributeDefinitionTransferList.tsx";
import {getIndexById, useAttributeDefinitionStore} from "../../../store/attributeDefinitionStore.ts";
import {useTranslation} from 'react-i18next';
import FullPageLoader from "../../Ui/FullPageLoader.tsx";
import {Button, Container} from "@mui/material";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";

type Props = {
    id: string;
    data: AttributeList;
} & DialogTabProps;

export default function OrganizeAttributeList({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const load = useAttributeDefinitionStore(state => state.load);

    React.useEffect(() => {
        load(t);
    }, [load]);

    const {definitions, loaded} = useAttributeDefinitionStore(state => ({
        definitions: state.definitions,
        loaded: state.loaded,
    }));
    const definitionsIndex = getIndexById();

    const {
        sortList,
        removeFromList,
        addToList,
    } = useAttributeListStore(state => ({
        sortList: state.sortList,
        removeFromList: state.removeFromList,
        addToList: state.addToList,
    }));

    if (!loaded) {
        return <FullPageLoader/>;
    }

    return (
        <>
            <DialogContent>
                <Container
                    sx={{
                        pt: 2,
                        minHeight,
                    }}
                >
                    <AttributeDefinitionTransferList
                        definitions={definitions}
                        definitionsIndex={definitionsIndex}
                        list={data.items!}
                        onSort={(items) => {
                            sortList(data.id, items);
                        }}
                        onAdd={(items) => {
                            addToList(data.id, items);
                        }}
                        onRemove={(items) => {
                            removeFromList(data.id, items);
                        }}
                    />
                </Container>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>
                    {t('dialog.close', 'Close')}
                </Button>
            </DialogActions>
        </>
    );
}
