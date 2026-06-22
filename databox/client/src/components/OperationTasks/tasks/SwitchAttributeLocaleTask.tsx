import {FormRow} from '@alchemy/react-form';
import {TaskComponentProps} from './taskTypes.ts';
import AttributeDefinitionSelect from '../../Form/AttributeDefinitionSelect.tsx';
import WorkspaceSelect from '../../Form/WorkspaceSelect.tsx';

export default function SwitchAttributeLocaleTask({
    usedFormSubmit,
}: TaskComponentProps) {
    const {control, watch} = usedFormSubmit;

    const workspaceId = watch('workspaceId');

    return (
        <>
            <FormRow>
                <WorkspaceSelect control={control} name={'workspaceId'} />
            </FormRow>
            {workspaceId ? (
                <FormRow>
                    <AttributeDefinitionSelect
                        key={`definition-select-${workspaceId}`}
                        workspaceId={workspaceId}
                        control={control}
                        name={'definitionId'}
                    />
                </FormRow>
            ) : null}
        </>
    );
}
