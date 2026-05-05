import FormDialog from '../Dialog/FormDialog';
import {ProfileForm} from '../Form/ProfileForm.tsx';
import {Profile} from '../../types';
import {useFormSubmit} from '@alchemy/api';
import {postProfile} from '../../api/profile.ts';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {useProfileStore} from '../../store/profileStore.ts';
import {useUserPreferencesStore} from '../../store/userPreferencesStore.ts';

type Props = {
    onCreate?: (data: Profile) => void;
} & StackedModalProps;

export default function CreateProfileDialog({onCreate, ...modalProps}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const addProfile = useProfileStore(state => state.addProfile);
    const preferences = useUserPreferencesStore(state => state.preferences);

    const usedFormSubmit = useFormSubmit<Profile>({
        defaultValues: {
            title: '',
        },
        onSubmit: async (data: Profile) => {
            return await postProfile({
                ...data,
                data: preferences,
            });
        },
        onSuccess: data => {
            toast.success(
                t('form.profile_create.success', 'Profile created!') as string
            );
            addProfile(data);
            closeModal();

            onCreate?.(data);
        },
    });

    const {submitting, remoteErrors, forbidNavigation} = usedFormSubmit;
    useDirtyFormPrompt(forbidNavigation, modalProps.modalIndex);
    const formId = 'create-attr-list';

    return (
        <FormDialog
            {...modalProps}
            title={t('form.profile_create.title', 'Create Profile')}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
        >
            <ProfileForm formId={formId} usedFormSubmit={usedFormSubmit} />
        </FormDialog>
    );
}
