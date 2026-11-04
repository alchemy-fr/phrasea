'use client';

import {useCallback, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import type {AttributeEntity} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {deleteAssetAttribute, patchAssetAttribute} from '@/lib/api/assets';
import {putAttributeEntity} from '@/lib/api/metadata';
import type {FaceCardFace} from './FaceCard';

/**
 * What a user does to the faces of an asset (see `FaceCard`), the asset view
 * refreshed after each of them.
 */
export function useFaceActions(assetId: string) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const refresh = useCallback(
        () =>
            queryClient.invalidateQueries({queryKey: ['asset-view', assetId]}),
        [queryClient, assetId]
    );

    return useMemo(() => {
        const failed = (e: any) => {
            toast.error(
                e?.message ??
                    t('faces.name_error', 'The face could not be named')
            );
        };

        const assign = async (
            face: FaceCardFace,
            entityId: string,
            name?: string
        ) => {
            try {
                const updated = await patchAssetAttribute(face.id, {
                    value: entityId,
                });
                await refresh();
                toast.success(
                    t('faces.named_toast', {
                        defaultValue: 'Face named {{name}}',
                        name: name ?? updated.value?.value ?? '',
                    })
                );
            } catch (e: any) {
                failed(e);
                throw e;
            }
        };

        const name = async (
            face: FaceCardFace,
            value: string
        ): Promise<AttributeEntity> => {
            try {
                const saved = await putAttributeEntity(face.entity!.id, {
                    value,
                    status: AttributeEntityStatus.Approved,
                });
                await refresh();
                toast.success(
                    t('faces.named_toast', {
                        defaultValue: 'Face named {{name}}',
                        name: saved.value,
                    })
                );

                return saved;
            } catch (e: any) {
                failed(e);
                throw e;
            }
        };

        const remove = async (face: FaceCardFace) => {
            try {
                await deleteAssetAttribute(face.id);
                await refresh();
                toast.success(t('faces.forgotten', 'Face forgotten'));
            } catch (e: any) {
                toast.error(e?.message);
                throw e;
            }
        };

        return {refresh, assign, name, remove};
    }, [t, refresh]);
}
