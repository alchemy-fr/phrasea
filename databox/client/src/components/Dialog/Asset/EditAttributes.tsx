import React from 'react';
import {Asset} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import AttributesEditorForm from "../../Media/Asset/Attribute/AttributesEditorForm";

type Props = {
    data: Asset;
} & DialogTabProps;

export default function EditAttributes({
    data,
    onClose,
    minHeight,
}: Props) {
    return <AttributesEditorForm
        workspaceId={data.workspace.id}
        assetId={data.id}
        onClose={onClose}
        minHeight={minHeight}
    />
}
