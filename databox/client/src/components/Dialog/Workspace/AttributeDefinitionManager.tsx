import React from 'react';
import {AttributeDefinition, Workspace} from "../../../types";
import {getWorkspaceAttributeDefinitions} from "../../../api/asset";
import {Checkbox, FormControlLabel, ListItemIcon, ListItemText, TextField} from "@mui/material";
import FormRow from "../../Form/FormRow";
import DefinitionManager, {DefinitionItemProps} from "./DefinitionManager";
import {useTranslation} from 'react-i18next';
import TextFieldsIcon from '@mui/icons-material/TextFields';
import {SvgIconComponent} from "@mui/icons-material";
import CheckBoxIcon from '@mui/icons-material/CheckBox';
import CalendarTodayIcon from '@mui/icons-material/CalendarToday';
import AlternateEmailIcon from '@mui/icons-material/AlternateEmail';
import LooksOneIcon from '@mui/icons-material/LooksOne';

const icons: Record<string, SvgIconComponent> = {
    text: TextFieldsIcon,
    boolean: CheckBoxIcon,
    date: CalendarTodayIcon,
    datetime: CalendarTodayIcon,
    number: LooksOneIcon,
    ip: AlternateEmailIcon,
}

function Item({data}: DefinitionItemProps<AttributeDefinition>) {
    return <form>
        <FormRow>
            <TextField
                name={'name'}
                label={'Name'}
                value={data.name}
            />
        </FormRow>
        <FormRow>
            <FormControlLabel
                control={<Checkbox
                    checked={data.searchable}
                />}
                label={`Searchable`}
                labelPlacement="end"
            />
        </FormRow>
        <FormRow>
            <FormControlLabel
                control={<Checkbox
                    checked={data.translatable}
                />}
                label={`Translatable`}
                labelPlacement="end"
            />
        </FormRow>
        <FormRow>
            <FormControlLabel
                control={<Checkbox
                    checked={data.multiple}
                />}
                label={`Multiple values`}
                labelPlacement="end"
            />
        </FormRow>
        <FormRow>
            <FormControlLabel
                control={<Checkbox
                    checked={data.allowInvalid}
                />}
                label={`Allow invalid values`}
                labelPlacement="end"
            />
        </FormRow>
        <FormRow>
            <TextField
                fullWidth={true}
                multiline={true}
                name={'fallback'}
                label={'Fallback'}
                value={data.fallback?.['en'] ?? ''}
            />
        </FormRow>
    </form>
}

function ListItem({data}: DefinitionItemProps<AttributeDefinition>) {
    return <>
        <ListItemIcon>
            {React.createElement(icons[data.fieldType || 'text'] ?? icons.text)}
        </ListItemIcon>
        <ListItemText
        primary={data.name}
        secondary={data.fieldType}
    />
        </>
}

type Props = {
    data: Workspace;
    onClose: () => void;
    minHeight?: number | undefined;
};

function createNewItem(): Partial<AttributeDefinition> {
    return {
        name: '',
        multiple: false,
        translatable: false,
        allowInvalid: false,
        searchable: true,
        fieldType: 'text',
    }
}

export default function AttributeDefinitionManager({
                                                       data,
                                                       minHeight,
                                                       onClose,
                                                   }: Props) {
    const {t} = useTranslation();
    return <DefinitionManager
        itemComponent={Item}
        listComponent={ListItem}
        load={() => getWorkspaceAttributeDefinitions(data.id)}
        minHeight={minHeight}
        onClose={onClose}
        createNewItem={createNewItem}
        newLabel={t('attribute_definitions.new.label', 'New attribute')}
    />
}
