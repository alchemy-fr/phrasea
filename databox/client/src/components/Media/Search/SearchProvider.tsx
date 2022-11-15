import React, {PropsWithChildren, useCallback, useState} from "react";
import {SearchContext} from "./SearchContext";
import {BucketKeyValue, extractLabelValueFromKey, FacetType} from "../Asset/Facets";
import {Filters, OrderBy} from "./Filter";
import {hashToQuery, queryToHash} from "./search";
import useHash from "../../../lib/useHash";

export default function SearchProvider({children}: PropsWithChildren<{}>) {
    const [hash, setHash] = useHash();
    const [reloadInc, setReloadInc] = useState(0);
    const {query, filters, collectionId, workspaceId, orderBy} = hashToQuery(hash);

    const resolvedOrderBy: OrderBy[] = orderBy.length === 0 ? [
        {
            a: 'createdAt',
            t: 'Creation date',
            w: 1,
        }
    ] : orderBy;

    const selectWorkspace = useCallback((workspaceId: string | undefined, forceReload?: boolean): void => {
        if (!setHash(queryToHash(query, filters, orderBy, workspaceId, undefined)) && forceReload) {
            setReloadInc(p => p + 1);
        }
    }, [setHash, query, filters, orderBy, collectionId]);

    const selectCollection = useCallback((collectionId: string | undefined, forceReload?: boolean): void => {
        if (!setHash(queryToHash(query, filters, orderBy, undefined, collectionId)) && forceReload) {
            setReloadInc(p => p + 1);
        }
    }, [setHash, query, filters, orderBy, workspaceId]);

    const setAttrFilters = useCallback((handler: (prev: Filters) => Filters): void => {
        setHash(queryToHash(query,  handler(filters), orderBy, workspaceId, collectionId));
    }, [setHash, query, filters, orderBy, workspaceId, collectionId]);

    const setOrderBy = useCallback((handler: (prev: OrderBy[]) => OrderBy[]): void => {
        setHash(queryToHash(query, filters, handler(resolvedOrderBy), workspaceId, collectionId));
    }, [setHash, query, filters, orderBy, workspaceId, collectionId]);

    const setQuery = useCallback((handler: string | ((prev: string) => string), forceReload?: boolean): void => {
        if (!setHash(queryToHash(typeof handler === 'string' ? handler : handler(query), filters, orderBy, workspaceId, collectionId))) {
            setReloadInc(p => p + 1);
        }
    }, [setHash, query, filters, orderBy, workspaceId, collectionId]);

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

    const toggleAttrFilter = (attrName: string, keyValue: BucketKeyValue, attrTitle: string): void => {
        setAttrFilters(prev => {
            const f = [...prev];

            const key = f.findIndex(_f => _f.a === attrName && !_f.i);

            if (key >= 0) {
                const {value} = extractLabelValueFromKey(keyValue);

                const tf = f[key];
                if (tf.v.find(v => extractLabelValueFromKey(v).value === value)) {
                    if (tf.v.length === 1) {
                        f.splice(key, 1);
                    } else {
                        tf.v = tf.v.filter(v => extractLabelValueFromKey(v).value !== value);
                    }
                } else {
                    tf.v = tf.v.concat(keyValue);
                }
            } else {
                f.push({
                    t: attrTitle,
                    a: attrName,
                    v: [keyValue],
                });
            }

            return f;
        });
    };

    const setAttrFilter = (attrName: string, values: BucketKeyValue[], attrTitle: string, widget?: FacetType): void => {
        setAttrFilters(prev => {
            const f = [...prev];

            const key = f.findIndex(_f => _f.a === attrName);

            if (key >= 0) {
                f[key].v = values;
            } else {
                f.push({
                    t: attrTitle,
                    a: attrName,
                    v: values,
                    w: widget,
                });
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
        searchChecksum: JSON.stringify({query, filters, collectionId, workspaceId}),
        reloadInc,
        orderBy: resolvedOrderBy,
        setOrderBy,
    }}>
        {children}
    </SearchContext.Provider>
}
