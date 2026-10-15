import AssetsPage from '@/app/(app)/assets/page';
import {WorkflowViewRoute} from '@/features/workflows/WorkflowViewRoute';

/**
 * `/workflows/:id` and `/workflows/:id/jobs/:jobId` (the job selected, see
 * `WorkflowViewRoute`): the path below the run is read client-side.
 */
export default async function Page({params}: {params: Promise<{id: string}>}) {
    const {id} = await params;

    return (
        <>
            <AssetsPage />
            <WorkflowViewRoute workflowId={id} />
        </>
    );
}
