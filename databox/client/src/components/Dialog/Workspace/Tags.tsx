import React from 'react';
import {Workspace} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import TagManager from "../../Media/Collection/TagManager";
import {Box, Typography} from "@mui/material";
import TagFilterRules from "../../Media/TagFilterRule/TagFilterRules";
import ContentTab from "../Tabbed/ContentTab";
import {useTranslation} from 'react-i18next';

type Props = {
    workspace: Workspace;
} & DialogTabProps;


export default function Tags({
                                 workspace,
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
        <TagManager workspaceIri={workspace['@id']}/>
        <hr/>
        <Box sx={{
            mb: 2
        }}>
            <Typography variant={'h2'}>
                {t('tag_filter_rules.title', 'Tag filter rules')}
            </Typography>
            <TagFilterRules
                id={workspace.id}
                workspaceId={workspace.id}
                type={'workspace'}
            />
        </Box>
    </ContentTab>
}
