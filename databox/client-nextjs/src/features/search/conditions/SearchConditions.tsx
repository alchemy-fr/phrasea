'use client';

import {useTranslation} from 'react-i18next';
import {PlusIcon} from 'lucide-react';
import {useSearch} from '../SearchProvider';
import {Button} from '@/components/ui/button';
import {SearchConditionChip} from './SearchConditionChip';
import {useModals} from '@/components/modals/ModalProvider';
import {ConditionDialog} from './ConditionDialog';

export function SearchConditions() {
    const {t} = useTranslation();
    const search = useSearch();
    const {openModal} = useModals();

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            {search.conditions.map(condition => (
                <SearchConditionChip key={condition.id} condition={condition} />
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="h-7 rounded-full border-dashed px-2.5 text-xs"
                onClick={() => openModal(ConditionDialog, {search})}
            >
                <PlusIcon className="size-3.5" />{' '}
                {t('search.condition.add', 'Add condition')}
            </Button>
        </div>
    );
}
