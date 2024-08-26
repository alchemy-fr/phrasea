import AssetForm from '../AssetForm';
import {FormData, Target, UploadedFile} from '../../types.ts';
import {useTranslation} from 'react-i18next';

type Props = {
    target: Target;
    files: UploadedFile[];
    onSubmit: (data: FormData) => void;
    onCancel: () => void;
};

export default function UploadForm({target, files, onSubmit, onCancel}: Props) {
    const {t} = useTranslation();
    return (
        <>
            <p>
                {files.length}{' '}
                {t('upload_form.selected_files', `selected files.`)}
            </p>

            <AssetForm
                targetId={target.id}
                submitPath={'/form/validate'}
                onComplete={onSubmit}
                onCancel={onCancel}
            />
        </>
    );
}
