import React from 'react';
import {Collection, Workspace} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import TagManager from "../../Media/Collection/TagManager";
import {Box, Typography} from "@mui/material";
import TagRules from "../../Media/TagFilterRule/TagRules";
import ContentTab from "../Tabbed/ContentTab";
import {useTranslation} from 'react-i18next';
import FormSection from "../../Form/FormSection";

type Props = {
    data: Collection;
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
            <TagRules
                id={data.id}
                workspaceId={data.id}
                type={'workspace'}
            />
    </ContentTab>
}
