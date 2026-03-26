import {ResultContext} from './ResultContext';
import {PropsWithChildren, useContext, useEffect, useState} from 'react';
import {ESDebug, GetAssetOptions, getAssets} from '../../../api/asset';
import {Asset} from '../../../types';
import {SearchContext} from './SearchContext';
import {TFacets} from '../Asset/Facets';
import {SortBy} from './Filter';
import axios from 'axios';
import {AQLQueries} from './AQL/query.ts';
import {useAssetStore} from '../../../store/assetStore.ts';
import {useChannelRegistration} from '../../../lib/pusher.ts';
import {ChannelEvent, ChannelType} from '../../../api/channels.ts';
import {SortWay} from '../../../api/common.ts';
import {getResolvedSortBy} from './search.ts';

type UserSearchContext = {
    position?: string | undefined;
};

let lastController: AbortController;

type SearchParams = {
    query: string;
    sortBy: SortBy[];
    nextUrl?: string;
    conditions?: AQLQueries;
    searchContext?: UserSearchContext;
    savedSearch?: string;
};

async function search({
    query,
    sortBy,
    nextUrl,
    conditions,
    searchContext,
    savedSearch,
}: SearchParams): Promise<{
    result: Asset[];
    facets: TFacets;
    total: number;
    next?: string | null;
    debug: ESDebug;
}> {
    if (lastController) {
        lastController.abort();
    }

    lastController = new AbortController();

    const order: GetAssetOptions['order'] = {};
    sortBy.forEach(s => {
        order[s.a] = s.w === 1 ? SortWay.DESC : SortWay.ASC;
    });

    const groupBy = sortBy.filter(s => s.g).map(s => s.a);

    const options: GetAssetOptions = {
        query,
        url: nextUrl,
        conditions: conditions?.filter(c => !c.disabled).map(c => c.query),
        group: groupBy.length > 0 ? groupBy.slice(0, 1) : undefined,
        order,
        savedSearch,
    };

    if (searchContext) {
        options.context = {
            ...searchContext,
        };
    }

    const result = await getAssets(options, {
        signal: lastController.signal,
    });

    return {
        total: result.total,
        facets: result.facets!,
        result: result.result,
        next: result.next,
        debug: result.debug,
    };
}

type State = {
    pages: Asset[][];
    loading: boolean;
    facets?: TFacets;
    total?: number;
    next?: string | null;
    loadNext?: string;
    debug?: ESDebug;
};

type Props = PropsWithChildren<{
    savedSearch?: string | undefined;
}>;

export default function ResultProvider({children, savedSearch}: Props) {
    const searchContext = useContext(SearchContext)!;

    const [state, setState] = useState<State>({
        pages: [],
        loading: false,
    });

    const setLoading = (loading: boolean) =>
        setState(prev => ({
            ...prev,
            loading,
        }));

    const [setAssets, reloadAsset] = useAssetStore(s => [
        s.setAssets,
        s.reloadAsset,
    ]);

    useChannelRegistration(
        ChannelType.ASSETS,
        ChannelEvent.RENDITION_UPDATE,
        (event: {assetId: string}) => {
            reloadAsset(event.assetId);
        }
    );

    const doSearch = async (nextUrl?: string) => {
        setLoading(true);
        try {
            const r = await search({
                query: searchContext.query,
                sortBy: getResolvedSortBy(searchContext.sortBy),
                nextUrl,
                conditions: searchContext.conditions,
                searchContext: {
                    position: searchContext.geolocation,
                },
                savedSearch,
            });

            setAssets(r.result);

            setState(prevState => {
                return {
                    pages: nextUrl
                        ? prevState.pages.concat([r.result])
                        : [r.result],
                    next: r.next,
                    total: r.total,
                    loading: false,
                    facets: r.facets,
                    debug: r.debug,
                };
            });
        } catch (e: any) {
            if (!(e instanceof axios.Cancel)) {
                throw e;
            }
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        doSearch();
        // eslint-disable-next-line
    }, [searchContext.searchChecksum, searchContext.reloadInc]);

    return (
        <ResultContext.Provider
            value={{
                loading: state.loading,
                pages: state.pages,
                facets: state.facets,
                total: state.total,
                debug: state.debug,
                loadMore: state.next
                    ? async () => {
                          await doSearch(state.next!);
                      }
                    : undefined,
                reload: doSearch,
            }}
        >
            {children}
        </ResultContext.Provider>
    );
}
