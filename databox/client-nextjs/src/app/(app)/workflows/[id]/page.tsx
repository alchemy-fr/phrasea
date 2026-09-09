import AssetsPage from '@/app/(app)/assets/page';
import {WorkflowViewRoute} from '@/features/workflows/WorkflowViewRoute';

export default async function Page({params}: {params: Promise<{id: string}>}) {
    const {id} = await params;

    return (
        <>
            <AssetsPage />
            <WorkflowViewRoute workflowId={id} />
        </>
    );
}
