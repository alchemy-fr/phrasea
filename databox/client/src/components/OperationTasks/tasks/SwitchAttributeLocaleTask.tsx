import {FormRow} from '@alchemy/react-form';
import {TaskComponentProps} from './taskTypes.ts';
import AttributeDefinitionSelect from '../../Form/AttributeDefinitionSelect.tsx';
import WorkspaceSelect from '../../Form/WorkspaceSelect.tsx';
import {FormHelperText, TextField} from '@mui/material';

export default function SwitchAttributeLocaleTask({
    usedFormSubmit,
}: TaskComponentProps) {
    const {register, control, watch} = usedFormSubmit;

    const workspaceId = watch('workspaceId');

    return (
        <>
            <FormRow>
                <WorkspaceSelect control={control} name={'workspaceId'} />
            </FormRow>
            {workspaceId ? (
                <>
                    <FormRow>
                        <AttributeDefinitionSelect
                            key={`definition-select-${workspaceId}`}
                            workspaceId={workspaceId}
                            control={control}
                            name={'definitionId'}
                        />
                    </FormRow>
                    <FormRow>
                        <TextField
                            {...register('fromLocale', {
                                required: true,
                            })}
                            label={'From Locale'}
                            required={true}
                        />
                        <FormHelperText>
                            Use <code>_</code> for no locale
                        </FormHelperText>
                    </FormRow>
                    <FormRow>
                        <TextField
                            {...register('toLocale', {
                                required: true,
                            })}
                            label={'To Locale'}
                            required={true}
                        />
                        <FormHelperText>
                            Use <code>_</code> for no locale
                        </FormHelperText>
                    </FormRow>
                </>
            ) : null}
        </>
    );
}
