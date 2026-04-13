import React, {useContext, useState} from 'react';
import {ResultContext} from '../../Search/ResultContext.tsx';
import {List} from '@mui/material';
import {Classes} from '../types.ts';
import {FacetPreference, TFacets} from './facetTypes.ts';
import {useUserPreferencesStore} from '../../../../store/userPreferencesStore.ts';
import {FacetGroup} from './FacetGroup.tsx';
import SearchBar from '../../../Ui/SearchBar.tsx';
import SettingsIcon from '@mui/icons-material/Settings';
import IconButton from '@mui/material/IconButton';
import {useModals} from '@alchemy/navigation';
import FacetSettingsDialog from './FacetSettingsDialog.tsx';

const Facets = React.memo(function ({facets}: {facets: TFacets}) {
    const {openModal} = useModals();
    const preferences = useUserPreferencesStore(state => state.preferences);
    const updatePreference = useUserPreferencesStore(
        state => state.updatePreference
    );
    const [searchQuery, setSearchQuery] = useState('');

    const facetPrefs: FacetPreference[] = preferences.facets ?? [];

    const find = (name: string) => facetPrefs.find(p => p.name === name);

    const list = Object.entries(facets).filter(
        ([k, v]) => v.buckets.length > 0 && !find(k)?.hidden
    );

    const getOrder = (name: string) => find(name)?.order ?? 99999;

    list.sort(([k1], [k2]) => {
        return getOrder(k1) - getOrder(k2);
    });

    const onPinToggle = (name: string) => {
        updatePreference('facets', prev => {
            if (prev?.some(p => p.name === name)) {
                return prev.filter(p => p.name !== name);
            }

            return (prev ?? []).concat([
                {
                    name,
                    order:
                        Math.max(-1, ...(prev ?? []).map(p => p.order ?? -1)) +
                        1,
                },
            ]);
        });
    };

    const onHide = (name: string) => {
        updatePreference('facets', prev => {
            if (prev?.some(p => p.name === name)) {
                return prev.map(p =>
                    p.name === name
                        ? {
                              ...p,
                              hidden: true,
                          }
                        : p
                );
            }

            return (prev ?? []).concat([
                {
                    name,
                    hidden: true,
                },
            ]);
        });
    };

    const openSettings = () => {
        openModal(FacetSettingsDialog, {facets});
    };

    return (
        <>
            <SearchBar
                name={'facet-filter'}
                searchQuery={searchQuery}
                setSearchQuery={setSearchQuery}
                settings={
                    <>
                        <IconButton onClick={openSettings} size={'small'}>
                            <SettingsIcon fontSize={'inherit'} />
                        </IconButton>
                    </>
                }
            />
            <List
                disablePadding
                component="nav"
                aria-labelledby="nested-list-subheader"
                sx={theme => ({
                    [`.${Classes.facetGroup}`]: {
                        'borderBottom': `1px solid ${theme.palette.divider}`,
                        '.MuiListItemText-primary': {
                            fontSize: theme.typography.subtitle2.fontSize,
                            fontWeight: 'bold',
                        },
                    },
                    '.MuiListItemText-secondary': {
                        wordWrap: 'break-word',
                    },
                })}
            >
                {list.map(([k, v]) => {
                    const pref = find(k);

                    return (
                        <FacetGroup
                            key={k}
                            name={k}
                            facet={v}
                            onPinToggle={onPinToggle}
                            pinned={Boolean(pref && !pref.hidden)}
                            onHide={onHide}
                        />
                    );
                })}
            </List>
        </>
    );
});

export default function FacetsProxy() {
    const c = useContext(ResultContext);
    const {facets} = c;

    if (!facets) {
        return null;
    }

    return <Facets facets={facets} />;
}
