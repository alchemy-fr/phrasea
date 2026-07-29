import {FormRow} from '@alchemy/react-form';
import {TaskComponentProps} from './taskTypes.ts';
import WorkspaceSelect from '../../Form/WorkspaceSelect.tsx';

export default function ReanalyzeWorkspaceFilesTask({
    usedFormSubmit,
}: TaskComponentProps) {
    const {control} = usedFormSubmit;

    return (
        <>
            <FormRow>
                <WorkspaceSelect
                    label={'Workspace'}
                    control={control}
                    name={'workspaceId'}
                />
            </FormRow>
        </>
    );
}
