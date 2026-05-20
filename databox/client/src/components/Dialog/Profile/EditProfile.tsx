import {Profile} from '../../../types';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import FormTab from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {useFormPrompt} from '@alchemy/navigation';
import {ProfileForm} from '../../Form/ProfileForm.tsx';
import {putProfile} from '../../../api/profile.ts';
import {useProfileStore} from '../../../store/profileStore.ts';

type Props = {
    id: string;
    data: Profile;
} & DialogTabProps;

export default function EditProfile({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const updateProfile = useProfileStore(state => state.updateProfile);

    const usedFormSubmit = useFormSubmit({
        defaultValues: data,
        onSubmit: async (data: Profile) => {
            return await putProfile(data.id, data);
        },
        onSuccess: data => {
            updateProfile(data);

            toast.success(
                t('form.profile_edit.success', 'Profile edited!') as string
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
            <ProfileForm
                usedFormSubmit={usedFormSubmit}
                data={data}
                formId={formId}
            />
        </FormTab>
    );
}
