import {FieldValues} from 'react-hook-form';
import RSelectWidget, {RSelectProps, SelectOption} from './RSelect';
import {AssetDataTemplate, getAssetDataTemplates} from '../../api/templates';
import {OptionProps, components} from 'react-select';
import {Checkbox} from '@mui/material';

const Option = (props: OptionProps<SelectOption>) => {
    return (
        <components.Option {...props}>
            <Checkbox
                checked={props.isSelected}
                sx={{
                    mr: 1,
                }}
            />
            {props.data.label}
        </components.Option>
    );
};

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
    collectionId: string | undefined;
} & RSelectProps<TFieldValues, true>;

export default function AssetDataTemplateSelect<
    TFieldValues extends FieldValues
>({workspaceId, collectionId, ...rest}: Props<TFieldValues>) {
    const load = async (inputValue: string): Promise<SelectOption[]> => {
        const data = (
            await getAssetDataTemplates({
                workspace: workspaceId,
                collection: collectionId,
            })
        ).result;

        return data
            .map((t: AssetDataTemplate) => ({
                value: t.id,
                label: t.name,
            }))
            .filter(i =>
                i.label.toLowerCase().includes((inputValue || '').toLowerCase())
            );
    };

    return (
        <RSelectWidget<TFieldValues, true>
            cacheId={'asset-data-templates'}
            {...rest}
            components={{Option}}
            loadOptions={load}
            isMulti={true as any}
            key={`${workspaceId}-${collectionId ?? ''}`}
            closeMenuOnSelect={false}
            hideSelectedOptions={false}
        />
    );
}
