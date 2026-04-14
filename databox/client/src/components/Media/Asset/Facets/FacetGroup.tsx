import {FacetGroupProps, FacetType} from './facetTypes.ts';
import React, {useState} from 'react';
import {
    Collapse,
    ListItem,
    ListItemButton,
    ListItemText,
    Stack,
} from '@mui/material';
import {Classes} from '../types.ts';
import IconButton from '@mui/material/IconButton';
import PushPinIcon from '@mui/icons-material/PushPin';
import {ExpandLess, ExpandMore} from '@mui/icons-material';
import {facetWidgets, facetWidgetsByKey} from './facetWidgets.ts';
import VisibilityOffIcon from '@mui/icons-material/VisibilityOff';
import VisibilityIcon from '@mui/icons-material/Visibility';

type Props = {
    onPinToggle: (name: string) => void;
    toggleHide: (name: string) => void;
    pinned: boolean;
    open: boolean;
    hidden: boolean;
    toggleOpen: (name: string) => void;
} & FacetGroupProps;

export function FacetGroup({
    facet,
    name,
    onPinToggle,
    pinned,
    toggleHide,
    toggleOpen,
    open,
    hidden,
}: Props) {
    const widget =
        facetWidgetsByKey[name] ??
        facetWidgets[facet.meta.widget ?? FacetType.Text] ??
        facetWidgets[FacetType.Text];

    return (
        <>
            <ListItem className={Classes.facetGroup} disablePadding>
                <ListItemButton onClick={() => toggleOpen(name)}>
                    <ListItemText
                        primary={`${facet.meta.title}${facet.meta.locale ? ` (${facet.meta.locale})` : ''}`}
                        slotProps={
                            hidden
                                ? {
                                      primary: {
                                          sx: {
                                              color: 'error.main',
                                          },
                                      },
                                  }
                                : undefined
                        }
                    />
                    <Stack direction="row" spacing={0.5} alignItems="center">
                        <IconButton
                            onMouseDown={e => e.stopPropagation()}
                            onClick={e => {
                                e.stopPropagation();
                                onPinToggle(name);
                            }}
                            size="small"
                        >
                            {pinned ? (
                                <PushPinIcon
                                    fontSize="inherit"
                                    color="primary"
                                />
                            ) : (
                                <PushPinIcon fontSize="inherit" />
                            )}
                        </IconButton>
                        <IconButton
                            onMouseDown={e => e.stopPropagation()}
                            onClick={e => {
                                e.stopPropagation();
                                toggleHide(name);
                            }}
                            size="small"
                        >
                            {hidden ? (
                                <VisibilityIcon fontSize="inherit" />
                            ) : (
                                <VisibilityOffIcon fontSize="inherit" />
                            )}
                        </IconButton>
                    </Stack>
                    {open ? <ExpandLess /> : <ExpandMore />}
                </ListItemButton>
            </ListItem>
            <Collapse in={open} timeout="auto" unmountOnExit>
                {React.createElement(widget, {
                    facet,
                    name,
                })}
            </Collapse>
        </>
    );
}
