import {useMemo} from 'react';
import {
    EntitiesIndex,
    EntityCached,
    RequestEntities,
    useEntitiesStore,
} from '../../../../store/entitiesStore.ts';
import {parseAQLQuery} from './AQL.ts';
import {
    AQLQuery,
    astToString,
    replaceConstants,
    replaceFieldFromDefinitions,
    replaceIdFromEntities,
} from './query.ts';
import {AttributeDefinitionsIndex} from '../../../../store/attributeDefinitionStore.ts';
import {AQLQueryAST} from './aqlTypes.ts';
import deepmerge from 'deepmerge';
import {replaceEntities} from './entities.tsx';
import {useTranslation} from 'react-i18next';

type ResolvedASTs = {
    condition: AQLQuery;
    query: string;
};

type ASTContainer = {
    condition: AQLQuery;
    ast: AQLQueryAST | undefined;
};

type Props = {
    conditions: AQLQuery[];
    loaded: boolean;
    definitionsIndexBySlug: AttributeDefinitionsIndex | undefined;
    definitionsIndexBySearchSlug: AttributeDefinitionsIndex | undefined;
};

export function useResolveASTs({
    conditions,
    loaded,
    definitionsIndexBySlug,
    definitionsIndexBySearchSlug,
}: Props): ResolvedASTs[] {
    const {t} = useTranslation();
    const index = useEntitiesStore(s => s.index);
    const fetchUnresolved = useEntitiesStore(s => s.fetchUnresolved);
    const requestEntities = useEntitiesStore(s => s.requestEntities);
    const astContainers = useMemo<ASTContainer[]>(
        () =>
            conditions.map(c => ({
                condition: c,
                ast: parseAQLQuery(c.query),
            })),
        [conditions]
    );

    return useMemo<ResolvedASTs[]>(() => {
        if (!loaded) {
            return conditions.map(c => ({
                condition: c,
                query: c.query,
            }));
        }

        const resolvedAsts = astContainers.map(({ast, condition}) => {
            if (!ast) {
                return {
                    query: condition.query,
                    condition,
                };
            }

            const newAst = deepmerge({}, ast);

            replaceIdFromEntities(
                newAst,
                definitionsIndexBySearchSlug!,
                (iri: string) => {
                    const entity = index[iri];
                    if (undefined === entity) {
                        requestEntities([iri]);
                    } else if (typeof entity === 'object') {
                        return entity as EntityCached;
                    }
                }
            );

            replaceFieldFromDefinitions(newAst, definitionsIndexBySlug!);
            replaceConstants(newAst, t);

            return {
                query: replaceEntities(astToString(newAst)),
                condition,
            } as ResolvedASTs;
        });

        fetchUnresolved();

        return resolvedAsts;
    }, [astContainers, definitionsIndexBySlug, index, requestEntities]);
}

export type UsedResolvedEntities = {
    entities: EntitiesIndex;
    requestEntities: RequestEntities;
};
