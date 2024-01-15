import AssetForm from "../AssetForm";
import {FormData, Target, UploadedFile} from "../../types.ts";

type Props = {
    target: Target;
    files: UploadedFile[];
    onSubmit: (data: FormData) => void;
    onCancel: () => void;
};

export default function UploadForm({
    target,
    files,
    onSubmit,
    onCancel,
}: Props) {

    return <>
        <p>{files.length} selected files.</p>

        <AssetForm
            targetId={target.id}
            submitPath={'/form/validate'}
            onComplete={onSubmit}
            onCancel={onCancel}
        />
    </>
}
