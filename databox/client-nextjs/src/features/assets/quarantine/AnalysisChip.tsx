'use client';

import {useTranslation} from 'react-i18next';
import {Loader2Icon, ShieldXIcon} from 'lucide-react';
import type {ApiFile} from '@/types/api';
import {Badge} from '@/components/ui/misc';

export function AnalysisChip({file}: {file: ApiFile}) {
    const {t} = useTranslation();
    if (file.analysisPending) {
        return (
            <Badge variant="warning">
                <Loader2Icon className="animate-spin" />{' '}
                {t('file.analysis.pending', 'Analysis in progress…')}
            </Badge>
        );
    }
    if (file.accepted === false) {
        return (
            <Badge variant="destructive">
                <ShieldXIcon /> {t('file.analysis.rejected', 'Rejected')}
            </Badge>
        );
    }

    return null;
}
