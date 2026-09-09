import {useContext} from 'react';
import {getPath, useNavigate} from '@alchemy/navigation';
import {SearchContext} from '../components/Media/Search/SearchContext.tsx';
import {
    BuiltInAttributeEnum,
    queryToHash,
} from '../components/Media/Search/search.ts';
import {AQLQuery} from '../components/Media/Search/AQL/query.ts';
import {routes} from '../routes.ts';

export function useSimilarSearchCondition(): (assetId: string) => void {
    const searchContext = useContext(SearchContext);
    const navigate = useNavigate();

    return (assetId: string) => {
        const condition: AQLQuery = {
            id: BuiltInAttributeEnum.Similar,
            query: `${BuiltInAttributeEnum.Similar} = "${assetId}"`,
        };

        if (searchContext) {
            searchContext.upsertCondition(condition);
        } else {
            const hash = queryToHash(undefined, '', [condition], [], undefined);
            navigate(`${getPath(routes.assets)}#${hash}`);
        }
    };
}
