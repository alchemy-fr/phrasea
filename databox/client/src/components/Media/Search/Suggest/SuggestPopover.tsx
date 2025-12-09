import {Box, Paper} from '@mui/material';
import {UsedSuggest} from './useSuggest.tsx';
import {replaceHighlight} from '../../Asset/Attribute/AttributeHighlights.tsx';
import '@algolia/autocomplete-theme-classic';

type Props = {
    usedSuggest: UsedSuggest;
};

export default function SuggestPopover({usedSuggest}: Props) {
    const {autocomplete, autocompleteState} = usedSuggest;

    return (
        <Paper
            className={'aa-Panel'}
            elevation={4}
            style={{
                zIndex: 1,
                marginTop: 0,
                marginLeft: 47,
            }}
            {...(autocomplete.getPanelProps({}) as any)}
        >
            {autocompleteState.isOpen &&
                autocompleteState.collections.map((collection, index) => {
                    const {source, items} = collection;

                    return (
                        <Box
                            key={`source-${index}`}
                            className="aa-Source"
                            sx={{
                                '.aa-Item': {
                                    minWidth: `min(50vw, 300px)`,
                                    p: 1,
                                    small: {
                                        color: 'primary.main',
                                        textAlign: 'right',
                                        mt: 0.5,
                                        display: 'block',
                                    },
                                },
                            }}
                        >
                            {items.length > 0 && (
                                <ul
                                    className="aa-List"
                                    {...autocomplete.getListProps()}
                                >
                                    {items.map(item => (
                                        <li
                                            key={item.id}
                                            className="aa-Item"
                                            {...(autocomplete.getItemProps({
                                                item,
                                                source,
                                            }) as any)}
                                        >
                                            <div>
                                                <div>
                                                    {replaceHighlight(
                                                        item.hl,
                                                        'b' as any
                                                    )}
                                                </div>
                                                <small>{item.t}</small>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Box>
                    );
                })}
        </Paper>
    );
}
