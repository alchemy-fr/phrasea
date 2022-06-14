import React from 'react';
import {Collection} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import ContentTab from "../Tabbed/ContentTab";
import CollectionMoveSection from "../../Media/Collection/CollectionMoveSection";
import FormSection from "../../Form/FormSection";
import {Alert, Button, Typography} from "@mui/material";
import {useTranslation} from 'react-i18next';
import {deleteCollection} from "../../../api/collection";

type Props = {
    data: Collection;
} & DialogTabProps;

export default function Operations({
                                       data,
                                       onClose,
                                       minHeight,
                                   }: Props) {
    const {t} = useTranslation();
    return <ContentTab
        onClose={onClose}
        minHeight={minHeight}
    >
        <CollectionMoveSection
            collection={data}
            onMoved={() => {
                onClose();
            }}
        />
        <FormSection>
            <Alert
                color={'error'}
                sx={{
                    mb: 2
                }}
            >
                {t('danger_zone', 'Danger zone')}
            </Alert>
            <Typography variant={'h2'} sx={{mb: 1}}>
                {t('collection_delete.title', 'Delete collection')}
            </Typography>
            <Button
                onClick={async () => {
                    await deleteCollection(data.id);
                    onClose();
                }}
                color={'error'}
            >
                {t('collection_delete.title', 'Delete collection')}
            </Button>
        </FormSection>
    </ContentTab>
}
