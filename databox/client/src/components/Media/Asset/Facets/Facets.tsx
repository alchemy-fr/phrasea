import React, {useContext, useState} from 'react';
import {ResultContext} from '../../Search/ResultContext.tsx';
import {
    Box,
    Button,
    List,
    ListItem,
    ListItemText,
    MenuItem,
} from '@mui/material';
import {Classes} from '../types.ts';
import {FacetPreference, orderInfinity, TFacets} from './facetTypes.ts';
import {useUserPreferencesStore} from '../../../../store/userPreferencesStore.ts';
import {FacetGroup} from './FacetGroup.tsx';
import SearchBar from '../../../Ui/SearchBar.tsx';
import SettingsIcon from '@mui/icons-material/Settings';
import IconButton from '@mui/material/IconButton';
import {useModals} from '@alchemy/navigation';
import FacetSettingsDialog from './FacetSettingsDialog.tsx';
import {
    createUndo,
    hideFacet,
    togglePinFacet,
    unhideFacet,
} from './facetFunc.ts';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import {MoreActionsButton} from '@alchemy/phrasea-ui';

const Facets = React.memo(function ({facets}: {facets: TFacets}) {
    const {openModal} = useModals();
    const {t} = useTranslation();
    const [closedNodes, setClosedNodes] = useState<string[]>([]);
    const preferences = useUserPreferencesStore(state => state.preferences);
    const updatePreference = useUserPreferencesStore(
        state => state.updatePreference
    );
    const [searchQuery, setSearchQuery] = useState('');

    const facetPrefs: FacetPreference[] = preferences.facets ?? [];

    const find = (name: string) => facetPrefs.find(p => p.name === name);

    let list = Object.entries(facets);

    if (searchQuery) {
        list = list.filter(([_k, v]) =>
            v.meta.title.toLowerCase().includes(searchQuery.toLowerCase())
        );
    } else {
        list = list.filter(
            ([k, v]) => v.buckets.length > 0 && !find(k)?.hidden
        );
    }

    const getOrder = (name: string) => find(name)?.order ?? orderInfinity;

    list.sort(([k1], [k2]) => {
        return getOrder(k1) - getOrder(k2);
    });

    const onPinToggle = (name: string) => {
        togglePinFacet(updatePreference, name);
    };

    const onHide = (name: string) => {
        const undo = createUndo(
            updatePreference,
            preferences.facets ?? [],
            name
        );
        hideFacet(updatePreference, name);
        const toastId = `facet-hidden-${name}`;

        const facetName = facets[name]?.meta.title || name;

        toast.success(
            <span>
                {t(
                    'facets.action.hidden.success',
                    '{{facetName}} facet has been hidden',
                    {facetName}
                )}
                <Button
                    onClick={() => {
                        undo();
                        toast.dismiss(toastId);
                        toast.info(
                            t(
                                'facets.action.hidden.undo',
                                '{{facetName}} facet restored',
                                {facetName}
                            )
                        );
                    }}
                >
                    {t('facets.action.undo', 'Undo')}
                </Button>
            </span>,
            {
                toastId,
                type: 'info',
            }
        );
    };

    const onUnhide = (name: string) => {
        unhideFacet(updatePreference, name);
    };

    const openSettings = () => {
        openModal(FacetSettingsDialog, {facets});
    };

    const expandAll = () => {
        setClosedNodes([]);
    };
    const collapseAll = () => {
        setClosedNodes(Object.entries(facets).map(([k]) => k));
    };

    return (
        <>
            <SearchBar
                name={'facet-filter'}
                searchQuery={searchQuery}
                setSearchQuery={setSearchQuery}
                settings={
                    <Box sx={{pl: 1, display: 'flex', alignItems: 'center'}}>
                        <IconButton onClick={openSettings} size={'small'}>
                            <SettingsIcon fontSize={'inherit'} />
                        </IconButton>
                        <MoreActionsButton
                            vertical={true}
                            iconButtonProps={{
                                size: 'small',
                            }}
                        >
                            {closeWrapper => [
                                <MenuItem
                                    key={'expandAll'}
                                    onClick={closeWrapper(expandAll)}
                                >
                                    {t('facets.action.expandAll', 'Expand All')}
                                </MenuItem>,
                                <MenuItem
                                    key={'collapseAll'}
                                    onClick={closeWrapper(collapseAll)}
                                >
                                    {t(
                                        'facets.action.collapseAll',
                                        'Collapse All'
                                    )}
                                </MenuItem>,
                            ]}
                        </MoreActionsButton>
                    </Box>
                }
                placeholder={t('common.filter.placeholder', 'Filter…')}
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
                    const hidden = Boolean(pref?.hidden);

                    return (
                        <FacetGroup
                            key={k}
                            name={k}
                            facet={v}
                            hidden={hidden}
                            onPinToggle={onPinToggle}
                            pinned={Boolean(pref && !pref.hidden)}
                            toggleHide={hidden ? onUnhide : onHide}
                            open={!closedNodes.includes(k)}
                            toggleOpen={name => {
                                setClosedNodes(prev => {
                                    if (prev.includes(name)) {
                                        return prev.filter(n => n !== name);
                                    } else {
                                        return [...prev, name];
                                    }
                                });
                            }}
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
