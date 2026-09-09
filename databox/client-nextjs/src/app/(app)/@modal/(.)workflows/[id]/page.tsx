import {WorkflowViewRoute} from '@/features/workflows/WorkflowViewRoute';

export default async function Modal({params}: {params: Promise<{id: string}>}) {
    const {id} = await params;

    return <WorkflowViewRoute workflowId={id} />;
}
