import FormDialog from '../Dialog/FormDialog';
import {DisplayProfileForm} from '../Form/DisplayProfileForm.tsx';
import {DisplayProfile} from '../../types';
import {useFormSubmit} from '@alchemy/api';
import {postProfile} from '../../api/profile.ts';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {useUserPreferencesStore} from '../../store/userPreferencesStore.ts';

type Props = {
    onCreate?: (data: DisplayProfile) => void;
} & StackedModalProps;

export default function CreateProfileDialog({onCreate, ...modalProps}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const {profile: _profile, ...preferences} = useUserPreferencesStore(
        state => state.preferences
    );

    const usedFormSubmit = useFormSubmit<DisplayProfile>({
        defaultValues: {
            name: '',
        },
        onSubmit: async (data: DisplayProfile) => {
            return await postProfile({
                ...data,
                data: preferences,
            });
        },
        onSuccess: data => {
            toast.success(
                t(
                    'form.display_profile.create_success',
                    'Display Profile created!'
                ) as string
            );
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
            title={t(
                'form.display_profile.create.title',
                'Create Display Profile'
            )}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
        >
            <DisplayProfileForm
                formId={formId}
                usedFormSubmit={usedFormSubmit}
            />
        </FormDialog>
    );
}
