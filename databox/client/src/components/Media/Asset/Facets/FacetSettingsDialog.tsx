import {Button, List, ListItem, ListItemText, Typography} from '@mui/material';
import React, {useCallback, useMemo, useState} from 'react';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useUserPreferencesStore} from '../../../../store/userPreferencesStore.ts';
import {Facet, FacetPreference, orderInfinity, TFacets} from './facetTypes.ts';
import VisibilityIcon from '@mui/icons-material/Visibility';
import IconButton from '@mui/material/IconButton';
import {closestCenter, DndContext} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';
import VisibilityOffIcon from '@mui/icons-material/VisibilityOff';
import {hideFacet, togglePinFacet, unhideFacet} from './facetFunc.ts';
import DragIndicatorIcon from '@mui/icons-material/DragIndicator';
import RestartAltIcon from '@mui/icons-material/RestartAlt';
import PushPinIcon from '@mui/icons-material/PushPin';

type Props = {facets: TFacets} & StackedModalProps;

function SortableListItem({id, children, ...props}: any) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({id});

    return (
        <ListItem
            ref={setNodeRef}
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
                opacity: isDragging ? 0.5 : 1,
                background: isDragging ? '#f0f0f0' : undefined,
            }}
            {...props}
        >
            <DragIndicatorIcon
                {...attributes}
                {...listeners}
                sx={{mr: 2, cursor: 'grab', outline: '0'}}
                tabIndex={-1}
            />
            {children}
        </ListItem>
    );
}

type PrefFacet = {
    name: string;
    facet: Facet;
    pref?: FacetPreference;
};

type HiddenFacet = {
    pref: FacetPreference;
} & Omit<PrefFacet, 'pref'>;

export default function FacetSettingsDialog({modalIndex, open, facets}: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();
    const preferences = useUserPreferencesStore(state => state.preferences);
    const updatePreference = useUserPreferencesStore(
        state => state.updatePreference
    );
    const facetPrefs: FacetPreference[] = useMemo(
        () => preferences.facets ?? [],
        [preferences]
    );

    const {visible, hidden} = useMemo(() => {
        const visible: PrefFacet[] = [];
        const hidden: HiddenFacet[] = [];

        Object.entries(facets).forEach(([name, facet]) => {
            const pref = facetPrefs.find(fp => fp.name === name);
            if (pref) {
                if (pref.hidden) {
                    hidden.push({name, facet, pref});
                } else {
                    visible.push({name, facet, pref});
                }
            } else {
                visible.push({name, facet});
            }
        });

        visible.sort(
            (a, b) =>
                (a.pref?.order ?? orderInfinity) -
                (b.pref?.order ?? orderInfinity)
        );

        return {
            visible,
            hidden,
        };
    }, [facetPrefs, facets]);

    const [items, setItems] = useState(() => visible.map(i => i.name));
    React.useEffect(() => {
        setItems(visible.map(i => i.name));
    }, [visible]);

    const handleDragEnd = useCallback(
        (event: any) => {
            const {active, over} = event;
            const moved = active.id;
            if (over?.id && moved !== over.id) {
                const oldIndex = items.indexOf(moved);
                const newIndex = items.indexOf(over.id);
                const newItems = arrayMove(items, oldIndex, newIndex);
                setItems(newItems);
                // Update order in preferences
                updatePreference('facets', prev => {
                    const orderMap = Object.fromEntries(
                        newItems.map((name, idx) => [name, idx])
                    );

                    const prevList = prev ? [...prev] : [];
                    if (!prevList.some(i => i.name === moved)) {
                        const unitIndex = newItems.findIndex(i => i === moved);
                        for (let i = 0; i <= unitIndex; i++) {
                            if (!prevList.some(pi => pi.name === newItems[i])) {
                                prevList.push({
                                    name: newItems[i],
                                });
                            }
                        }
                    }

                    return prevList.map(fp => {
                        if (
                            Object.prototype.hasOwnProperty.call(
                                orderMap,
                                fp.name
                            )
                        ) {
                            return {...fp, order: orderMap[fp.name]};
                        }

                        return fp;
                    });
                });
            }
        },
        [items, updatePreference]
    );

    const handleUnhide = useCallback(
        (name: string) => {
            unhideFacet(updatePreference, name);
        },
        [updatePreference]
    );

    const handleHide = useCallback(
        (name: string) => {
            hideFacet(updatePreference, name);
        },
        [updatePreference]
    );

    const onPinToggle = useCallback(
        (name: string) => {
            togglePinFacet(updatePreference, name);
        },
        [updatePreference]
    );

    return (
        <AppDialog
            maxWidth={'sm'}
            modalIndex={modalIndex}
            open={open}
            onClose={closeModal}
            title={t('facets.settings.title', 'Facet Settings')}
            actions={({onClose}) => (
                <>
                    <Button
                        onClick={() => {
                            if (
                                !window.confirm(
                                    t(
                                        'facets.settings.reset_confirm',
                                        'Are you sure you want to reset facet settings?'
                                    )
                                )
                            ) {
                                return;
                            }
                            updatePreference('facets', undefined);
                            onClose();
                        }}
                        color={'error'}
                        startIcon={<RestartAltIcon />}
                    >
                        {t('facets.settings.reset', 'Reset to default')}
                    </Button>
                    <Button onClick={onClose}>
                        {t('dialog.close', 'Close')}
                    </Button>
                </>
            )}
        >
            <Typography variant="subtitle2" sx={{mb: 1}}>
                {t('facets.settings.visible', 'Visible facets')}
            </Typography>
            <DndContext
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
            >
                <SortableContext
                    items={items}
                    strategy={verticalListSortingStrategy}
                >
                    <List>
                        {items.map(name => {
                            const {facet, pref} =
                                visible.find(v => v.name === name) ?? {};
                            if (!facet) {
                                return null;
                            }

                            return (
                                <SortableListItem
                                    key={name}
                                    id={name}
                                    secondaryAction={
                                        <>
                                            <IconButton
                                                onMouseDown={e =>
                                                    e.stopPropagation()
                                                }
                                                onClick={e => {
                                                    e.stopPropagation();
                                                    onPinToggle(name);
                                                }}
                                            >
                                                {pref ? (
                                                    <PushPinIcon color="primary" />
                                                ) : (
                                                    <PushPinIcon />
                                                )}
                                            </IconButton>

                                            <IconButton
                                                onMouseDown={e => {
                                                    e.stopPropagation();
                                                }}
                                                onClick={e => {
                                                    e.stopPropagation();
                                                    handleHide(name);
                                                }}
                                            >
                                                <VisibilityOffIcon />
                                            </IconButton>
                                        </>
                                    }
                                >
                                    <ListItemText primary={facet.meta.title} />
                                </SortableListItem>
                            );
                        })}
                    </List>
                </SortableContext>
            </DndContext>
            {hidden.length > 0 && (
                <>
                    <Typography variant="subtitle2" sx={{mt: 2, mb: 1}}>
                        {t('facets.settings.hidden', 'Hidden facets')}
                    </Typography>
                    <List>
                        {hidden.map(({facet, pref}) => (
                            <ListItem
                                key={pref.name}
                                secondaryAction={
                                    <IconButton
                                        onClick={() => handleUnhide(pref.name)}
                                    >
                                        <VisibilityIcon />
                                    </IconButton>
                                }
                            >
                                <ListItemText
                                    primary={facet.meta.title}
                                    slotProps={{
                                        primary: {
                                            sx: {color: 'error.main'},
                                        },
                                    }}
                                />
                            </ListItem>
                        ))}
                    </List>
                </>
            )}
        </AppDialog>
    );
}
