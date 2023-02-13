import React from 'react';
import {Workspace} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import AclForm from "../../Acl/AclForm";
import ContentTab from "../Tabbed/ContentTab";

type Props = {
    data: Workspace;
} & DialogTabProps;

export default function Acl({
    data,
    onClose,
    minHeight,
}: Props) {
    return <ContentTab
        onClose={onClose}
        minHeight={minHeight}
        disableGutters={true}
    >
        <AclForm
            objectId={data.id}
            objectType={'workspace'}
        />
    </ContentTab>
}
