import {FlexRow} from '@alchemy/phrasea-ui';
import {RSelectWidget} from '@alchemy/react-form';
import {Control, FieldValues, UseFormRegister, useWatch} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import RenditionDefinitionSelect from '../RenditionDefinitionSelect.tsx';

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
        case 'hide_rendition':
            form = (
                <>
                    <RenditionDefinitionSelect
                        useIRI={false}
                        workspaceId={workspaceId}
                        control={control}
                        name={`${path}.renditionId` as any}
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
                <RSelectWidget
                    label={t('form.asset_policy.action.label', 'Action')}
                    control={control}
                    name={actionPath as any}
                    options={[
                        {
                            label: t(
                                'form.asset_policy.actions.hide_rendition',
                                'Hide Rendition'
                            ),
                            value: 'hide_rendition',
                        },
                    ]}
                />
                {form}
            </FlexRow>
        </>
    );
}
