'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {RefreshCwIcon} from 'lucide-react';
import {toast} from 'sonner';
import {EntityName} from '@/types/api';
import {getESDocument, syncESDocument} from '@/lib/api/assets';
import {Button} from '@/components/ui/button';
import {Badge, Skeleton} from '@/components/ui/misc';
import {CopyButton} from '@/components/ui/copy-button';

/**
 * Raw Elasticsearch document of an entity (tech role).
 */
export function ESDocumentTab({
    asset,
    entity = EntityName.Asset,
    id,
}: {
    asset?: {id: string};
    entity?: string;
    id?: string;
}) {
    const {t} = useTranslation();
    const entityId = id ?? asset!.id;
    const doc = useQuery({
        queryKey: ['es-document', entity, entityId],
        queryFn: () => getESDocument(entity, entityId),
    });
    const json = doc.data ? JSON.stringify(doc.data.data, null, 2) : '';

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                {doc.data ? (
                    <Badge variant={doc.data.synced ? 'success' : 'warning'}>
                        {doc.data.synced
                            ? t('es.synced', 'Synced')
                            : t('es.not_synced', 'Out of sync')}
                    </Badge>
                ) : null}
                <Button
                    variant="outline"
                    size="sm"
                    className="ml-auto"
                    onClick={async () => {
                        await syncESDocument(entity, entityId);
                        toast.success(
                            t('es.sync_requested', 'Synchronization requested')
                        );
                        setTimeout(() => doc.refetch(), 1500);
                    }}
                >
                    <RefreshCwIcon /> {t('es.sync', 'Synchronize')}
                </Button>
                {json ? <CopyButton value={json} /> : null}
            </div>
            {doc.isLoading ? (
                <Skeleton className="h-64" />
            ) : (
                <pre className="max-h-[60vh] overflow-auto rounded-md bg-muted p-3 font-mono text-xs">
                    {json}
                </pre>
            )}
        </div>
    );
}
