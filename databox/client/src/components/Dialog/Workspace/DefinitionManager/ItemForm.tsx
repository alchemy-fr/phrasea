import {toast} from 'react-toastify';
import React, {FunctionComponent} from 'react';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {useFormSubmit} from '@alchemy/api';
import {RemoteErrors} from '@alchemy/react-form';
import {StateSetter, Workspace} from '../../../../types.ts';
import {useTranslation} from 'react-i18next';
import {DefaultValues} from 'react-hook-form';
import {
    DefinitionBase,
    DefinitionItemFormProps,
    DefinitionManagerExtraProps,
    NormalizeData,
} from './managerTypes.ts';

type Props<D extends DefinitionBase, EP extends DefinitionManagerExtraProps> = {
    item: D;
    itemComponent: FunctionComponent<DefinitionItemFormProps<D, EP>>;
    onSave: (data: D) => Promise<D>;
    workspace: Workspace;
    formId: string;
    onItemUpdate: (data: D) => void;
    setSubmitting: StateSetter<boolean>;
    normalizeData?: NormalizeData<D>;
    denormalizeData?: NormalizeData<D>;
    extraProps: EP;
};

export default function ItemForm<
    D extends DefinitionBase,
    EP extends DefinitionManagerExtraProps,
>({
    item,
    formId,
    itemComponent,
    onSave,
    workspace,
    onItemUpdate,
    setSubmitting,
    normalizeData,
    denormalizeData,
    extraProps,
}: Props<D, EP>) {
    const {t} = useTranslation();
    const usedFormSubmit = useFormSubmit({
        defaultValues: item as DefaultValues<D>,
        onSubmit: async (data: D) => {
            setSubmitting(true);
            try {
                const newData = await onSave(
                    denormalizeData ? denormalizeData(data) : data
                );
                const n = normalizeData ? normalizeData(newData) : newData;
                onItemUpdate(n);

                const fullReset = () => {
                    const newValues: Record<string, any> = {...getValues()};
                    Object.keys(newValues).forEach(key => {
                        newValues[key] = null;
                    });

                    Object.entries(n).forEach(([key, value]) => {
                        newValues[key] = value;
                    });

                    return newValues;
                };

                reset(fullReset() as any);

                return n;
            } finally {
                setSubmitting(false);
            }
        },
        onSuccess: () => {
            toast.success(t('definition_manager.saved', 'Saved!') as string);
        },
    });

    const {remoteErrors, forbidNavigation, reset, getValues} = usedFormSubmit;

    useDirtyFormPrompt(Boolean(item) && forbidNavigation);

    return (
        <form id={formId} onSubmit={usedFormSubmit.handleSubmit}>
            {React.createElement(itemComponent, {
                data: item,
                onSave,
                onItemUpdate,
                usedFormSubmit,
                workspace,
                extraProps,
            })}
            <RemoteErrors errors={remoteErrors} />
        </form>
    );
}
