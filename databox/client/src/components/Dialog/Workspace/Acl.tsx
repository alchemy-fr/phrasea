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
                            }: Props) {
    return <ContentTab
        onClose={onClose}
    >
        <AclForm
            objectId={workspace.id}
            objectType={'workspace'}
        />
    </ContentTab>
}
