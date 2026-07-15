import {FlexRow} from '@alchemy/phrasea-ui';
import {FormRow, RSelectWidget} from '@alchemy/react-form';
import {Control, FieldValues, UseFormRegister, useWatch} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import RenditionDefinitionSelect from '../RenditionDefinitionSelect.tsx';
import AttributeDefinitionSelect from '../AttributeDefinitionSelect.tsx';
import {AssetPolicyActionName} from '../../../types.ts';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
    path: string;
    control: Control<TFieldValues>;
    register: UseFormRegister<TFieldValues>;
};

export default function AssetPolicyActionWidget<
    TFieldValues extends FieldValues,
>({path, control, workspaceId}: Props<TFieldValues>) {
    const {t} = useTranslation();
    const actionPath = `${path}.action`;
    const watchedAction = useWatch({
        name: actionPath as any,
        control,
    });

    let form;
    switch (watchedAction) {
        case AssetPolicyActionName.HideRendition:
            form = (
                <>
                    <RenditionDefinitionSelect
                        useIRI={false}
                        workspaceId={workspaceId}
                        control={control}
                        name={`${path}.definitionId` as any}
                    />
                </>
            );
            break;
        case AssetPolicyActionName.HideAttribute:
            form = (
                <>
                    <AttributeDefinitionSelect
                        useIRI={false}
                        workspaceId={workspaceId}
                        control={control}
                        name={`${path}.definitionId` as any}
                    />
                </>
            );
            break;
        default:
            form = <></>;
    }

    return (
        <>
            <FlexRow gap={1}>
                <FormRow>
                    <RSelectWidget
                        label={t(
                            'form.asset_policy.actions.action.label',
                            'Action'
                        )}
                        control={control}
                        name={actionPath as any}
                        options={[
                            {
                                label: t(
                                    'form.asset_policy.actions.action.hide_rendition',
                                    'Hide Rendition'
                                ),
                                value: AssetPolicyActionName.HideRendition,
                            },
                            {
                                label: t(
                                    'form.asset_policy.actions.action.hide_attribute',
                                    'Hide Attribute'
                                ),
                                value: AssetPolicyActionName.HideAttribute,
                            },
                        ]}
                    />
                </FormRow>
                <div>{form}</div>
            </FlexRow>
        </>
    );
}
