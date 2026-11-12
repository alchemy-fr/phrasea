'use client';

import {useCallback} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import type {Publication} from '@/types/api';
import {useConfirm} from '@alchemy/next-ui/ui/confirm';
import {deletePublication, publicationKeys} from '@/lib/api/publications';
import {useTranslateField} from '@/lib/utils/translate';

/** Deletes a publication, once the user typed its title to confirm */
export function useDeletePublication() {
    const {t} = useTranslation();
    const confirm = useConfirm();
    const queryClient = useQueryClient();
    const translate = useTranslateField();

    return useCallback(
        (publication: Publication) => {
            const title =
                publication.title ||
                translate(publication, 'title') ||
                publication.id;

            return confirm({
                title: t('publication.delete.title', 'Delete publication'),
                description: t(
                    'publication.delete.description',
                    'The publication "{{title}}", its assets and its sub-publications will be deleted. This cannot be undone.',
                    {title}
                ),
                confirmLabel: t('common.delete', 'Delete'),
                destructive: true,
                textToType: title,
                onConfirm: async () => {
                    await deletePublication(publication.id);
                    await queryClient.invalidateQueries({
                        queryKey: publicationKeys.all,
                    });
                    toast.success(
                        t('publication.delete.done', 'Publication deleted')
                    );
                },
            });
        },
        [confirm, queryClient, t, translate]
    );
}
