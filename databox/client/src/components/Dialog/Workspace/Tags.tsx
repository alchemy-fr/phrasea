import React from 'react';
import {Workspace} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import TagManager from "../../Media/Collection/TagManager";
import {Box, Typography} from "@mui/material";
import TagFilterRules from "../../Media/TagFilterRule/TagFilterRules";
import ContentTab from "../Tabbed/ContentTab";

type Props = {
    workspace: Workspace;
} & DialogTabProps;

export default function Tags({
                                 workspace,
                                 onClose,
                             }: Props) {
    return <ContentTab
        onClose={onClose}
    >
        <div>
            <h4>Manage tags</h4>
            <TagManager workspaceIri={workspace['@id']}/>
        </div>
        <hr/>
        <Box sx={{
            mb: 2
        }}>
            <Typography variant={'h2'}>Tag filter rules</Typography>
            <TagFilterRules
                id={workspace.id}
                workspaceId={workspace.id}
                type={'workspace'}
            />
        </Box>
    </ContentTab>
}
