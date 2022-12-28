import React from 'react';
import {Collection} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import ContentTab from "../Tabbed/ContentTab";
import {Divider, MenuList} from "@mui/material";
import KeyIcon from '@mui/icons-material/Key';
import EventIcon from '@mui/icons-material/Event';
import InfoRow from "../Info/InfoRow";

type Props = {
    id: string;
    data: Collection;
} & DialogTabProps;

export default function InfoCollection({
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
                label={'Creation date'}
                value={data.createdAt}
                icon={<EventIcon/>}
            />
            <InfoRow
                label={'Modification date'}
                value={data.updatedAt}
                icon={<EventIcon/>}
            />
        </MenuList>
    </ContentTab>
}
