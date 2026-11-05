import {Suspense} from 'react';
import {AuditLogScreen} from '@/features/audit-log/AuditLogScreen';
import {FullPageLoader} from '@/components/ui/loader';

export default function AuditLogPage() {
    return (
        <Suspense fallback={<FullPageLoader />}>
            <AuditLogScreen />
        </Suspense>
    );
}
