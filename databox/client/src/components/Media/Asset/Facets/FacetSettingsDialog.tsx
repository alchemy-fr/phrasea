import {Button, List, ListItem, ListItemText} from '@mui/material';
import React from 'react';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useUserPreferencesStore} from '../../../../store/userPreferencesStore.ts';
import {Facet, FacetPreference, TFacets} from './facetTypes.ts';
import VisibilityIcon from '@mui/icons-material/Visibility';
import IconButton from '@mui/material/IconButton';

type Props = {facets: TFacets} & StackedModalProps;

type ResolvedFacetPref = {
    facet: Facet;
    pref: FacetPreference;
};

export default function FacetSettingsDialog({modalIndex, open, facets}: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    const preferences = useUserPreferencesStore(state => state.preferences);
    const facetPrefs: FacetPreference[] = preferences.facets ?? [];

    const nodes: ResolvedFacetPref[] = facetPrefs
        .map(fp => {
            const facet = facets[fp.name];
            if (facet) {
                return {
                    facet,
                    pref: fp,
                };
            }
        })
        .filter(n => !!n);

    return (
        <AppDialog
            maxWidth={'sm'}
            modalIndex={modalIndex}
            open={open}
            onClose={closeModal}
            title={t('facets.settings.title', 'Facet Settings')}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose}>
                        {t('dialog.close', 'Close')}
                    </Button>
                </>
            )}
        >
            <List>
                {nodes.map(({facet, pref}) => (
                    <ListItem
                        secondaryAction={
                            <>
                                {pref.hidden ? (
                                    <IconButton>
                                        <VisibilityIcon />
                                    </IconButton>
                                ) : null}
                            </>
                        }
                    >
                        <ListItemText
                            primary={facet.meta.title}
                            secondary={
                                pref.hidden
                                    ? t('facets.settings.hidden', 'Hidden')
                                    : null
                            }
                            secondaryTypographyProps={{color: 'error'}}
                        />
                    </ListItem>
                ))}
            </List>
        </AppDialog>
    );
}
