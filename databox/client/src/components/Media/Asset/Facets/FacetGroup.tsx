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

type Props = {
    onPinToggle: (name: string) => void;
    onHide: (name: string) => void;
    pinned: boolean;
} & FacetGroupProps;

export function FacetGroup({facet, name, onPinToggle, pinned, onHide}: Props) {
    const [open, setOpen] = useState(true);

    const widget =
        facetWidgetsByKey[name] ??
        facetWidgets[facet.meta.widget ?? FacetType.Text] ??
        facetWidgets[FacetType.Text];

    return (
        <>
            <ListItem className={Classes.facetGroup} disablePadding>
                <ListItemButton onClick={() => setOpen(o => !o)}>
                    <ListItemText
                        primary={`${facet.meta.title}${facet.meta.locale ? ` (${facet.meta.locale})` : ''}`}
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
                                onHide(name);
                            }}
                            size="small"
                        >
                            <VisibilityOffIcon fontSize="inherit" />
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
