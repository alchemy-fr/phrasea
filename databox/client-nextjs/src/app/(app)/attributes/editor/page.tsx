import AssetsPage from '@/app/(app)/assets/page';
import {AttributeBatchEditorRoute} from '@/features/attributes/batch/AttributeBatchEditorRoute';

export default function Page() {
    return (
        <>
            <AssetsPage />
            <AttributeBatchEditorRoute />
        </>
    );
}
