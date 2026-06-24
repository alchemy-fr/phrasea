import {FormRow} from '@alchemy/react-form';
import {TaskComponentProps} from './taskTypes.ts';
import WorkspaceSelect from '../../Form/WorkspaceSelect.tsx';

export default function IndexAssetsTask({usedFormSubmit}: TaskComponentProps) {
    const {control} = usedFormSubmit;

    return (
        <>
            <FormRow>
                <WorkspaceSelect control={control} name={'workspaceId'} />
            </FormRow>
        </>
    );
}
