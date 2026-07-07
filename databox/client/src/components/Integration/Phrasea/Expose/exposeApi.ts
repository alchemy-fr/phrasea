import {getHydraCollection} from '@alchemy/api';
import {QueryAndPaginationParams} from '../../../../api/types.ts';
import {apiClient} from '../../../../init.ts';
import {ExposeProfile, ExposePublication} from './exposeType.ts';

export async function getExposeProfiles({
    nextUrl,
    integrationId,
}: {integrationId: string} & QueryAndPaginationParams) {
    return getHydraCollection<ExposeProfile>(
        (
            await apiClient.get(
                nextUrl ??
                    `/integrations/expose/${integrationId}/proxy/profiles`
            )
        ).data
    );
}

export async function getExposePublications({
    nextUrl,
    integrationId,
}: {integrationId: string} & QueryAndPaginationParams) {
    return getHydraCollection<ExposePublication>(
        (
            await apiClient.get(
                nextUrl ??
                    `/integrations/expose/${integrationId}/proxy/publications`
            )
        ).data
    );
}
