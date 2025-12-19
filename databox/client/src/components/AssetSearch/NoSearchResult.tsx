import {Trans} from 'react-i18next';
import CancelPresentationIcon from '@mui/icons-material/CancelPresentation';
import {Avatar, Typography} from '@mui/material';
import {FlexRow} from '@alchemy/phrasea-ui';

type Props = {};

export default function NoSearchResult({}: Props) {
    return (
        <div
            style={{
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                color: '#777',
            }}
        >
            <div>
                <FlexRow gap={2}>
                    <Avatar>
                        <CancelPresentationIcon />
                    </Avatar>
                    <Typography variant={'h2'}>
                        <Trans i18nKey={'search.no_results_message'}>
                            We are sorry, but your search did not return{' '}
                            <strong>any assets</strong>!
                        </Trans>
                    </Typography>
                </FlexRow>

                <div>
                    <p>Suggestions to refine your search:</p>
                    <ul>
                        <li>
                            <Trans i18nKey={'search.suggestion.check_spelling'}>
                                <strong>Check Spelling</strong>: Ensure all
                                search terms are spelled correctly.
                            </Trans>
                        </li>
                        <li>
                            <Trans
                                i18nKey={
                                    'search.suggestion.consider_using_facets'
                                }
                            >
                                <strong>Consider Using Facets</strong>: Click on
                                the different facet values to narrow down or
                                expand the results (workspace, collection, tags,
                                dates, etc.).
                            </Trans>
                        </li>
                        <li>
                            <Trans i18nKey={'search.suggestion.modify_filters'}>
                                <strong>Modify Filters</strong>: If you have
                                applied filters, remove or change them to
                                broaden the search scope.
                            </Trans>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    );
}
