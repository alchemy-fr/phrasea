import {Target, UploadedFile} from '../../types.ts';
import {OnSubmitForm} from './UploadStepper.tsx';
import AssetForm from './AssetForm.tsx';
import {Container} from '@mui/material';

type Props = {
    target: Target;
    files: UploadedFile[];
    onSubmit: OnSubmitForm;
    onCancel: () => void;
};

export default function UploadForm({target, onSubmit, onCancel}: Props) {
    return (
        <Container>
            <AssetForm
                targetId={target.id}
                submitPath={'/form/validate'}
                formDataKey={'data'}
                onComplete={onSubmit}
                onCancel={onCancel}
            />
        </Container>
    );
}
