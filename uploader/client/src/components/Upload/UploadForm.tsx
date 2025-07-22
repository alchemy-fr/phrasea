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
                {t('upload_form.selected_files', {
                    defaultValue: '{{count}} selected files',
                    count: files.length,
                })}
            </p>

            <AssetForm
                targetId={target.id}
                submitPath={'/form/validate'}
                formDataKey={'data'}
                onComplete={onSubmit}
                onCancel={onCancel}
            />
        </>
    );
}
