import {FormRow} from '@alchemy/react-form';
import {TaskComponentProps} from './taskTypes.ts';
import AttributeDefinitionSelect from '../../Form/AttributeDefinitionSelect.tsx';
import WorkspaceSelect from '../../Form/WorkspaceSelect.tsx';

export default function AttributeDefinitionTask({
    usedFormSubmit,
}: TaskComponentProps) {
    const {control, watch} = usedFormSubmit;

    const workspaceId = watch('workspaceId');

    return (
        <>
            <FormRow>
                <WorkspaceSelect
                    label={'Workspace'}
                    control={control}
                    name={'workspaceId'}
                />
            </FormRow>
            {workspaceId ? (
                <FormRow>
                    <AttributeDefinitionSelect
                        label={'Attribute'}
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
