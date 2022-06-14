import React from 'react';
import {Workspace} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import AclForm from "../../Acl/AclForm";
import ContentTab from "../Tabbed/ContentTab";

type Props = {
    workspace: Workspace;
} & DialogTabProps;

export default function Acl({
                                workspace,
                                onClose,
                                minHeight,
                            }: Props) {
    return <ContentTab
        onClose={onClose}
        minHeight={minHeight}
        disableGutters={true}
    >
        <AclForm
            objectId={workspace.id}
            objectType={'workspace'}
        />
    </ContentTab>
}
