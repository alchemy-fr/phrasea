import {Suspense} from 'react';
import {WorkflowsScreen} from '@/features/workflows/WorkflowsScreen';
import {FullPageLoader} from '@/components/ui/loader';

export default function WorkflowsPage() {
    return (
        <Suspense fallback={<FullPageLoader />}>
            <WorkflowsScreen />
        </Suspense>
    );
}
