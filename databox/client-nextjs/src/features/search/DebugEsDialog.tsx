'use client';

import {useTranslation} from 'react-i18next';
import type {ESDebug} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {CopyButton} from '@/components/ui/copy-button';

export function DebugEsDialog({
    open,
    onOpenChange,
    debug,
}: ModalProps & {debug: ESDebug}) {
    const {t} = useTranslation();
    const json = JSON.stringify(debug.query, null, 2);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="lg">
                <DialogHeader>
                    <DialogTitle>
                        {t('search.debug', 'Search debug')}
                    </DialogTitle>
                </DialogHeader>
                <DialogBody>
                    <div className="mb-3 flex items-center gap-4 text-sm text-muted-foreground">
                        <span>
                            {t(
                                'search.debug.es_time',
                                'Elasticsearch: {{ms}} ms',
                                {ms: debug.esQueryTime}
                            )}
                        </span>
                        <span>
                            {t('search.debug.total_time', 'Total: {{ms}} ms', {
                                ms: debug.totalResponseTime,
                            })}
                        </span>
                        <span className="ml-auto">
                            <CopyButton
                                value={json}
                                label={t('common.copy', 'Copy')}
                            />
                        </span>
                    </div>
                    <pre className="max-h-[60vh] overflow-auto rounded-md bg-muted p-3 font-mono text-xs">
                        {json}
                    </pre>
                </DialogBody>
            </DialogContent>
        </Dialog>
    );
}
