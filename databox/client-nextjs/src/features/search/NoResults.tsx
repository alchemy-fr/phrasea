'use client';

import {useTranslation} from 'react-i18next';
import {LogInIcon, SearchXIcon} from 'lucide-react';
import {EmptyState} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {useAuth} from '@/lib/auth/AuthProvider';
import {useSearch} from './SearchProvider';

export function NoResults() {
    const {t} = useTranslation();
    const search = useSearch();
    const {status, login} = useAuth();
    // Sorting alone is not a filter: only show the hints when something narrows the results.
    const hasFilters = Boolean(
        search.query || search.conditions.length > 0 || search.geolocation
    );

    return (
        <EmptyState
            className="h-full"
            testId="no-results"
            icon={<SearchXIcon />}
            title={t('search.no_results.title', 'No results')}
            description={
                hasFilters ? (
                    <ul className="list-disc space-y-1 text-left">
                        <li>
                            {t(
                                'search.no_results.spelling',
                                'Check the spelling of your query'
                            )}
                        </li>
                        <li>
                            {t(
                                'search.no_results.facets',
                                'Remove some facets or conditions'
                            )}
                        </li>
                        <li>
                            {t(
                                'search.no_results.filters',
                                'Try a broader workspace or collection'
                            )}
                        </li>
                    </ul>
                ) : (
                    t(
                        'search.no_results.empty',
                        'There are no assets to display.'
                    )
                )
            }
            action={
                search.hasSearch ? (
                    <Button variant="outline" onClick={search.reset}>
                        {t('search.clear_search', 'Clear search')}
                    </Button>
                ) : status === 'anonymous' ? (
                    // Nothing public to show: the assets may need an account
                    <Button
                        data-testid="no-results-sign-in"
                        onClick={() => login()}
                    >
                        <LogInIcon /> {t('user.login', 'Sign in')}
                    </Button>
                ) : null
            }
        />
    );
}
