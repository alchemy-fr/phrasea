import {DisplayProfile} from '../../../types';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import FormTab from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {useFormPrompt} from '@alchemy/navigation';
import {DisplayProfileForm} from '../../Form/DisplayProfileForm.tsx';
import {putProfile} from '../../../api/profile.ts';
import {useProfileStore} from '../../../store/profileStore.ts';

type Props = {
    id: string;
    data: DisplayProfile;
} & DialogTabProps;

export default function EditDisplayProfile({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const updateProfile = useProfileStore(state => state.updateProfile);

    const usedFormSubmit = useFormSubmit({
        defaultValues: data,
        onSubmit: async (data: DisplayProfile) => {
            return await putProfile(data.id, data);
        },
        onSuccess: data => {
            updateProfile(data);

            toast.success(
                t(
                    'form.display_profile.edit_success',
                    'Display Profile edited!'
                ) as string
            );
            onClose();
        },
    });

    const {submitting, remoteErrors, forbidNavigation} = usedFormSubmit;
    useFormPrompt(t, forbidNavigation);

    const formId = 'edit-attr-list';

    return (
        <FormTab
            onClose={onClose}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
            minHeight={minHeight}
        >
            <DisplayProfileForm
                usedFormSubmit={usedFormSubmit}
                data={data}
                formId={formId}
            />
        </FormTab>
    );
}
