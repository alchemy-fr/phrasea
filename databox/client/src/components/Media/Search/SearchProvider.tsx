import React, {PropsWithChildren, useCallback, useState} from "react";
import {SearchContext} from "./SearchContext";
import {extractLabelValueFromKey, FacetType, ResolvedBucketValue} from "../Asset/Facets";
import {FilterEntry, Filters, FilterType, SortBy} from "./Filter";
import {hashToQuery, queryToHash} from "./search";
import useHash from "../../../lib/useHash";

export function getResolvedSortBy(sortBy: SortBy[]): SortBy[] {
    return sortBy.length > 0 ? sortBy : [
        {
            a: 'createdAt',
            t: 'Creation date',
            w: 1,
            g: false,
        }
    ];
}

export default function SearchProvider({children}: PropsWithChildren<{}>) {
    const [hash, setHash] = useHash();
    const [reloadInc, setReloadInc] = useState(0);
    const {query, filters, collectionId, workspaceId, sortBy, geolocation} = hashToQuery(hash);
    const resolvedSortBy = getResolvedSortBy(sortBy);

    const selectWorkspace = useCallback((workspaceId: string | undefined, forceReload?: boolean): void => {
        if (!setHash(queryToHash(query, filters, sortBy, workspaceId, undefined, geolocation)) && forceReload) {
            setReloadInc(p => p + 1);
        }
    }, [setHash, query, filters, sortBy, collectionId, geolocation]);

    const selectCollection = useCallback((collectionId: string | undefined, forceReload?: boolean): void => {
        if (!setHash(queryToHash(query, filters, sortBy, undefined, collectionId, geolocation)) && forceReload) {
            setReloadInc(p => p + 1);
        }
    }, [setHash, query, filters, sortBy, workspaceId, geolocation]);

    const setAttrFilters = useCallback((handler: (prev: Filters) => Filters): void => {
        setHash(queryToHash(query, handler(filters), sortBy, workspaceId, collectionId, geolocation));
    }, [setHash, query, filters, sortBy, workspaceId, collectionId, geolocation]);

    const setSortBy = useCallback((newValue: SortBy[]): void => {
        setHash(queryToHash(query, filters, newValue, workspaceId, collectionId, geolocation));
    }, [setHash, query, filters, workspaceId, collectionId, geolocation]);

    const setQuery = useCallback((handler: string | ((prev: string) => string), forceReload?: boolean): void => {
        if (!setHash(queryToHash(typeof handler === 'string' ? handler : handler(query), filters, sortBy, workspaceId, collectionId, geolocation))) {
            setReloadInc(p => p + 1);
        }
    }, [setHash, query, filters, sortBy, workspaceId, collectionId, geolocation]);

    const setGeoLocation = React.useCallback((position: string | undefined) => {
        setHash(queryToHash(query, filters, sortBy, workspaceId, collectionId, position));
    }, [setHash, query, filters, sortBy, workspaceId, collectionId, geolocation]);

    const removeAttrFilter = (key: number): void => {
        setAttrFilters(prev => {
            const f = [...prev];
            f.splice(key, 1);

            return f;
        });
    };

    const invertAttrFilter = (key: number): void => {
        setAttrFilters(prev => {
            const f = [...prev];

            if (f[key].i) {
                delete f[key].i;
            } else {
                f[key].i = 1;
            }

            return f;
        });
    };

    const toggleAttrFilter = (
        attrName: string,
        type: FilterType | undefined,
        keyValue: ResolvedBucketValue,
        attrTitle: string
    ): void => {
        setAttrFilters(prev => {
            const f = [...prev];

            const key = f.findIndex(_f => _f.a === attrName && !_f.i);

            if (key >= 0) {
                const {value} = extractLabelValueFromKey(keyValue, type);

                const tf = f[key];
                if (tf.v.find(v => extractLabelValueFromKey(v, type).value === value)) {
                    if (tf.v.length === 1) {
                        f.splice(key, 1);
                    } else {
                        tf.v = tf.v.filter(v => extractLabelValueFromKey(v, type).value !== value);
                    }
                } else {
                    tf.v = tf.v.concat(keyValue);
                }
            } else {
                f.push({
                    t: attrTitle,
                    a: attrName,
                    v: [keyValue],
                    x: type,
                });
            }

            return f;
        });
    };

    const setAttrFilter = (
        attrName: string,
        type: FilterType | undefined,
        values: ResolvedBucketValue[],
        attrTitle: string,
        widget?: FacetType
    ): void => {
        setAttrFilters(prev => {
            const f = [...prev];

            const key = f.findIndex(_f => _f.a === attrName);

            if (key >= 0) {
                f[key].v = values;
            } else {
                const items: FilterEntry = {
                    t: attrTitle,
                    a: attrName,
                    v: values,
                    w: widget,
                    x: type,
                };
                f.push(items);
            }

            return f;
        });
    };

    return <SearchContext.Provider value={{
        selectWorkspace,
        selectCollection,
        workspaceId,
        collectionId,
        toggleAttrFilter,
        setAttrFilter,
        invertAttrFilter,
        removeAttrFilter,
        attrFilters: filters,
        query,
        setQuery,
        searchChecksum: JSON.stringify({
            query,
            filters,
            collectionId,
            workspaceId,
            sortBy: resolvedSortBy,
            geolocation,
        }),
        reloadInc,
        sortBy: resolvedSortBy,
        setSortBy: setSortBy,
        geolocation,
        setGeoLocation,
    }}>
        {children}
    </SearchContext.Provider>
}
