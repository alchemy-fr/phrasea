import {toast} from "react-toastify";
import React, {FunctionComponent} from "react";
import {useDirtyFormPrompt} from "../../Tabbed/FormTab.tsx";
import type {DefinitionBase} from "./DefinitionManager.tsx";
import {DefinitionItemFormProps} from "./DefinitionManager.tsx";
import {useFormSubmit} from '@alchemy/api';
import RemoteErrors from "../../../Form/RemoteErrors.tsx";
import {StateSetter, Workspace} from "../../../../types.ts";
import {useTranslation} from 'react-i18next';
import {DefaultValues} from "react-hook-form";

type Props<D extends DefinitionBase> = {
    item: D;
    itemComponent: FunctionComponent<DefinitionItemFormProps<D>>;
    onSave: (data: D) => Promise<D>;
    workspace: Workspace;
    formId: string;
    onItemUpdate: (data: D) => void;
    setSubmitting: StateSetter<boolean>;
};

export default function ItemForm<D extends DefinitionBase>({
    item,
    formId,
    itemComponent,
    onSave,
    workspace,
    onItemUpdate,
    setSubmitting,
}: Props<D>) {
    const {t} = useTranslation();
    const usedFormSubmit = useFormSubmit({
        defaultValues: item as DefaultValues<D>,
        onSubmit: async (data: D) => {
            setSubmitting(true);
            try {
                const newData = await onSave(data);
                onItemUpdate(newData);

                return newData;
            } finally {
                setSubmitting(false);
            }
        },
        onSuccess: () => {
            toast.success(
                t('definition_manager.saved', 'Definition saved!') as string
            );
        },
    });

    const {remoteErrors, forbidNavigation} = usedFormSubmit;

    useDirtyFormPrompt(Boolean(item) && forbidNavigation);

    return <>
        <form
            id={formId}
            onSubmit={usedFormSubmit.handleSubmit}
        >
            {React.createElement(itemComponent, {
                data: item,
                usedFormSubmit,
                workspace,
            })}
            <RemoteErrors errors={remoteErrors}/>
        </form>
    </>
}
