'use client';

import {useCallback} from 'react';
import {useTranslation} from 'react-i18next';
import {useConfirm} from '@/components/ui/confirm';
import {isApiError} from '@/lib/api/http';
import type {HierarchyConflictStrategy} from '@/lib/api/assets';

const CONFLICT_CODE = 'hierarchy_conflict';

/**
 * Messages of the hierarchy conflicts an attribute update was rejected with,
 * none when it failed for another reason.
 */
export function getHierarchyConflicts(e: unknown): string[] {
    if (!isApiError(e, 422)) {
        return [];
    }

    return ((e.data?.violations ?? []) as {code?: string; message: string}[])
        .filter(v => v.code === CONFLICT_CODE)
        .map(v => v.message);
}

/**
 * Runs an attribute update; when values disagree with the hierarchy of an
 * entity attribute (Pays=Belgique while Paris is selected), lists the
 * conflicts and runs it again with the `replace` strategy once confirmed.
 * Resolves to `undefined` when the user cancels.
 */
export function useHierarchyConflictConfirm() {
    const {t} = useTranslation();
    const confirm = useConfirm();

    return useCallback(
        async <T,>(
            run: (strategy?: HierarchyConflictStrategy) => Promise<T>
        ): Promise<T | undefined> => {
            try {
                return await run();
            } catch (e) {
                const conflicts = getHierarchyConflicts(e);
                if (conflicts.length === 0) {
                    throw e;
                }

                let result: T | undefined;
                const confirmed = await confirm({
                    title: t(
                        'attribute.hierarchy.conflict.title',
                        'Values disagree with the hierarchy'
                    ),
                    description: t(
                        'attribute.hierarchy.conflict.description',
                        'Replacing keeps the values you have just entered and removes or replaces the incompatible ones.'
                    ),
                    confirmLabel: t(
                        'attribute.hierarchy.conflict.replace',
                        'Replace'
                    ),
                    destructive: true,
                    children: (
                        <ul className="list-disc space-y-1 pl-5 text-sm">
                            {conflicts.map((m, i) => (
                                <li key={i}>{m}</li>
                            ))}
                        </ul>
                    ),
                    onConfirm: async () => {
                        result = await run('replace');
                    },
                });

                return confirmed ? result : undefined;
            }
        },
        [confirm, t]
    );
}
