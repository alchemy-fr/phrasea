import React from 'react';
import {Collection} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import TagRules from "../../Media/TagFilterRule/TagRules";
import ContentTab from "../Tabbed/ContentTab";
import {useTranslation} from 'react-i18next';

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
