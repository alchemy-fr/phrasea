import {FlexRow} from '@alchemy/phrasea-ui';
import {FormRow, RSelectWidget} from '@alchemy/react-form';
import {Control, FieldValues, UseFormRegister, useWatch} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import CollectionTreeWidget from '../CollectionTreeWidget.tsx';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
    path: string;
    control: Control<TFieldValues>;
    register: UseFormRegister<TFieldValues>;
};

export default function AssetPolicyConditionWidget<
    TFieldValues extends FieldValues,
>({path, control, workspaceId}: Props<TFieldValues>) {
    const {t} = useTranslation();
    const fieldPath = `${path}.field`;
    const watchedField = useWatch({
        name: fieldPath as any,
        control,
    });

    let form;
    switch (watchedField) {
        case 'collection':
            form = (
                <>
                    <CollectionTreeWidget
                        workspaceId={workspaceId}
                        control={control}
                        name={`${path}.collection` as any}
                    />
                </>
            );
            break;
        default:
            form = <></>;
    }

    return (
        <>
            <FlexRow
                gap={1}
                style={{
                    alignItems: 'start',
                }}
            >
                <FormRow>
                    <RSelectWidget
                        label={t(
                            'form.asset_policy.conditions.field.label',
                            'Field'
                        )}
                        control={control}
                        autoFocus={false}
                        name={`${path}.field` as any}
                        options={[
                            {
                                label: t(
                                    'form.asset_policy.conditions.field.collection',
                                    'Collection'
                                ),
                                value: 'collection',
                            },
                        ]}
                    />
                </FormRow>
                <FormRow>
                    <RSelectWidget
                        label={t(
                            'form.asset_policy.conditions.operator.label',
                            'Operator'
                        )}
                        autoFocus={false}
                        control={control}
                        name={`${path}.operator` as any}
                        options={[
                            {
                                label: t(
                                    'form.asset_policy.conditions.operator.equals',
                                    'Equals'
                                ),
                                value: '=',
                            },
                        ]}
                    />
                </FormRow>
                <div>{form}</div>
            </FlexRow>
        </>
    );
}
