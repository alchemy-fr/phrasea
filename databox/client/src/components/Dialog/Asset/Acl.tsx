import React from 'react';
import {Asset, Collection} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import AclForm from "../../Acl/AclForm";
import ContentTab from "../Tabbed/ContentTab";

type Props = {
    data: Asset;
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
            objectType={'asset'}
        />
    </ContentTab>
}
