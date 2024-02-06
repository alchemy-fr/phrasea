import {AutocompleteOptions, AutocompleteState, createAutocomplete} from '@algolia/autocomplete-core';
import React, {ReactNode} from 'react';
import {Paper} from "@mui/material";
import '@algolia/autocomplete-theme-classic';
import {replaceHighlight} from "../Asset/Attribute/Attributes.tsx";
import {SearchSuggestion} from "../../../api/asset.ts";
import Box from "@mui/material/Box";


type Props = {
    children: (props: any) => ReactNode;
    getSources: AutocompleteOptions<SearchSuggestion>['getSources'];
};

export default function AutoComplete({
    getSources,
    children,
}: Props) {
    const [autocompleteState, setAutocompleteState] = React.useState<AutocompleteState<SearchSuggestion>>({} as AutocompleteState<SearchSuggestion>);

    const autocomplete = React.useMemo(
        () =>
            createAutocomplete<SearchSuggestion>({
                onStateChange({state}) {
                    setAutocompleteState(state);
                },
                getSources,
            }),
        []
    );

    return (
        <div
            className={'aa-Autocomplete'}
            {...(autocomplete.getRootProps({}) as any)}
        >
            <div className={'aa-InputWrapper'}>
                {children(autocomplete.getInputProps({
                    inputElement: null
                }))}
            </div>
            <Paper
                elevation={5}
                className={'aa-Panel'}
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
                                        p: 1,
                                        'small': {
                                            color: 'secondary.main',
                                            ml: 1,
                                            mt: 1,
                                            display: 'block',
                                        }
                                    }
                                }}
                            >
                                {items.length > 0 && (
                                    <ul
                                        className="aa-List"
                                        {...autocomplete.getListProps()}
                                    >
                                        {items.map((item) => (
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
                                                        {replaceHighlight(item.hl, 'b' as any)}
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
        </div>
    );
}
