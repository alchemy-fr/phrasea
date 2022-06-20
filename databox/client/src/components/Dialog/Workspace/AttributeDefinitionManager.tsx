import React from 'react';
import {AttributeDefinition, Workspace} from "../../../types";
import {getWorkspaceAttributeDefinitions, putAttributeDefinition} from "../../../api/asset";
import {ListItemIcon, ListItemText, TextField} from "@mui/material";
import FormRow from "../../Form/FormRow";
import DefinitionManager, {DefinitionItemProps} from "./DefinitionManager";
import {useTranslation} from 'react-i18next';
import TextFieldsIcon from '@mui/icons-material/TextFields';
import {SvgIconComponent} from "@mui/icons-material";
import CheckBoxIcon from '@mui/icons-material/CheckBox';
import CalendarTodayIcon from '@mui/icons-material/CalendarToday';
import AlternateEmailIcon from '@mui/icons-material/AlternateEmail';
import LooksOneIcon from '@mui/icons-material/LooksOne';
import {useForm} from "react-hook-form";
import {mapApiErrors} from "../../../lib/form";
import FormFieldErrors from "../../Form/FormFieldErrors";
import CheckboxWidget from "../../Form/CheckboxWidget";

const icons: Record<string, SvgIconComponent> = {
    text: TextFieldsIcon,
    boolean: CheckBoxIcon,
    date: CalendarTodayIcon,
    datetime: CalendarTodayIcon,
    number: LooksOneIcon,
    ip: AlternateEmailIcon,
}

function Item({
                  data,
              }: DefinitionItemProps<AttributeDefinition>) {
    const {t} = useTranslation();

    const {
        register,
        handleSubmit,
        setError,
        control,
        formState: {errors}
    } = useForm<any>({
        defaultValues: data,
    });

    const onSubmit = async (data: AttributeDefinition) => {
        try {
            await putAttributeDefinition(data.id, data);
        } catch (e: any) {
            mapApiErrors(e, setError);
        }
    }

    return <form onSubmit={handleSubmit(onSubmit)}>
        <FormRow>
            <TextField
                label={'Name'}
                {...register('name')}
            />
            <FormFieldErrors
                field={'name'}
                errors={errors}
            />
        </FormRow>
        <FormRow>
            <CheckboxWidget
                label={t('form.attribute_definition.searchable.label', 'Searchable')}
                control={control}
                name={'searchable'}
            />
            <FormFieldErrors
                field={'searchable'}
                errors={errors}
            />
        </FormRow>
        <FormRow>
            <CheckboxWidget
                label={t('form.attribute_definition.translatable.label', 'Translatable')}
                control={control}
                name={'translatable'}
            />
            <FormFieldErrors
                field={'translatable'}
                errors={errors}
            />
        </FormRow>
        <FormRow>
            <CheckboxWidget
                label={t('form.attribute_definition.multiple.label', 'Multiple values')}
                control={control}
                name={'multiple'}
            />
            <FormFieldErrors
                field={'multiple'}
                errors={errors}
            />
        </FormRow>
        <FormRow>
            <CheckboxWidget
                label={t('form.attribute_definition.allowInvalid.label', 'Allow invalid values')}
                control={control}
                name={'allowInvalid'}
            />
            <FormFieldErrors
                field={'allowInvalid'}
                errors={errors}
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
