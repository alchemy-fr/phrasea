import React from 'react';
import {Workspace} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import TagRules from "../../Media/TagFilterRule/TagRules";
import ContentTab from "../Tabbed/ContentTab";

type Props = {
    data: Workspace;
} & DialogTabProps;


export default function TagRulesTab({
                                        data,
                                        onClose,
                                        minHeight,
                                    }: Props) {
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
