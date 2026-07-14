import {FieldValues} from 'react-hook-form';
import {AssetDataTemplate, getAssetDataTemplates} from '../../api/templates';
import {components, OptionProps} from 'react-select';
import {Checkbox} from '@mui/material';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import {usePaginatedSelectLoader} from '@alchemy/phrasea-framework';

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
} & AsyncRSelectProps<TFieldValues, true>;

export default function AssetDataTemplateSelect<
    TFieldValues extends FieldValues,
>({workspaceId, collectionId, ...rest}: Props<TFieldValues>) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getAssetDataTemplates({
                ...props,
                workspace: workspaceId,
                collection: collectionId,
            }),
        map: (t: AssetDataTemplate) => ({
            value: t.id,
            label: t.name,
        }),
        filterLabels: true,
    });

    const {t} = useTranslation();

    return (
        <AsyncRSelectWidget<TFieldValues, true>
            key={`${workspaceId}-${collectionId ?? ''}`}
            cacheId={'asset-data-templates'}
            {...rest}
            components={{Option}}
            loadOptions={loadOptions}
            isMulti={true as any}
            closeMenuOnSelect={false}
            hideSelectedOptions={false}
            noOptionsMessage={() =>
                t('form.asset.templates.no_options', `No template available`)
            }
        />
    );
}
