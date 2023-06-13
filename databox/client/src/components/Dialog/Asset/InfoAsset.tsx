import React from 'react';
import {Asset} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import ContentTab from "../Tabbed/ContentTab";
import {Divider, MenuList} from "@mui/material";
import KeyIcon from '@mui/icons-material/Key';
import EventIcon from '@mui/icons-material/Event';
import InfoRow from "../Info/InfoRow";

type Props = {
    data: Asset;
} & DialogTabProps;

export default function InfoAsset({
    data,
    onClose,
    minHeight,
}: Props) {
    return <ContentTab
        onClose={onClose}
        minHeight={minHeight}
    >
        <MenuList>
            <InfoRow
                label={'ID'}
                value={data.id}
                copyValue={data.id}
                icon={<KeyIcon/>}
            />
            <Divider/>
            <InfoRow
                label={'Date Added'}
                value={data.createdAt}
                copyValue={data.createdAt}
                icon={<EventIcon/>}
            />
            <InfoRow
                label={'Last Modification date'}
                value={data.editedAt}
                copyValue={data.editedAt}
                icon={<EventIcon/>}
            />
            <InfoRow
                label={'Last attribute modification date'}
                value={data.attributesEditedAt}
                copyValue={data.attributesEditedAt}
                icon={<EventIcon/>}
            />
        </MenuList>
    </ContentTab>
}
