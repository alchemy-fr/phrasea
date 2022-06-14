import React from 'react';
import {Workspace} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import TagManager from "../../Media/Collection/TagManager";
import {Typography} from "@mui/material";
import ContentTab from "../Tabbed/ContentTab";
import {useTranslation} from 'react-i18next';

type Props = {
    data: Workspace;
} & DialogTabProps;


export default function Tags({
                                 data,
                                 onClose,
                                 minHeight,
                             }: Props) {
    const {t} = useTranslation();
    return <ContentTab
        onClose={onClose}
        minHeight={minHeight}
    >
        <Typography variant={'h2'}>
            {t('workspace.manage.tags.title', 'Workspace tags')}
        </Typography>
        <TagManager workspaceIri={data['@id']}/>
    </ContentTab>
}
