import React, {MouseEvent, useContext} from 'react';
import {Workspace} from '../../types';
import CollectionMenuItem from './CollectionMenuItem';
import {SearchContext} from './Search/SearchContext';
import {
    CircularProgress,
    Collapse,
    IconButton,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    ListSubheader,
} from '@mui/material';
import ExpandLessIcon from '@mui/icons-material/ExpandLess';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import EditIcon from '@mui/icons-material/Edit';
import CreateNewFolderIcon from '@mui/icons-material/CreateNewFolder';
import BusinessIcon from '@mui/icons-material/Business';
import CreateCollection from './Collection/CreateCollection';
import ModalLink from '../Routing/ModalLink';
import {useTranslation} from 'react-i18next';
import {useModals} from '@alchemy/navigation';
import {modalRoutes} from '../../routes';
import {useCollectionStore} from '../../store/collectionStore';
import {useShallow} from 'zustand/react/shallow';
import LoadMoreCollections from './Collection/LoadMoreCollections';

export type WorkspaceMenuItemProps = {
    data: Workspace;
};

export default function WorkspaceMenuItem({data}: WorkspaceMenuItemProps) {
    const {id, name, capabilities} = data;

    const {t} = useTranslation();
    const searchContext = useContext(SearchContext)!;
    const {openModal} = useModals();
    const selected = searchContext.workspaces.includes(id);
    const [expanded, setExpanded] = React.useState(false);

    const addCollection = useCollectionStore(state => state.addCollection);
    const loadMore = useCollectionStore(state => state.loadMore);
    const loadRoot = useCollectionStore(state => state.loadRoot);
    const pager = useCollectionStore(useShallow(state => state.tree))[id];

    const expand = (force?: boolean) => {
        setExpanded(p => !p || true === force);
    };
    const expandClick = (e: MouseEvent) => {
        e.stopPropagation();
        expand();

        if (e.detail > 1) {
            // is double click
            loadRoot(id);
        }
    };

    const onClick = () => {
        searchContext.selectWorkspace(id, name, selected);
        expand(true);
    };

    return (
        <>
            <ListSubheader
                component={'div'}
                disableGutters={true}
                className={'workspace-item'}
            >
                <ListItem
                    sx={{
                        'backgroundColor': 'primary.main',
                        'color': 'primary.contrastText',
                        '.c-action': {
                            visibility: 'hidden',
                        },
                        '&:hover .c-action': {
                            visibility: 'visible',
                        },
                        '.MuiListItemSecondaryAction-root': {
                            zIndex: 1,
                        },
                    }}
                    secondaryAction={
                        <>
                            {capabilities.canEdit && (
                                <IconButton
                                    color={'inherit'}
                                    title={t(
                                        'workspace.item.create_collection',
                                        'Add collection in this workspace'
                                    )}
                                    onClick={() =>
                                        openModal(CreateCollection, {
                                            workspaceId: id,
                                            workspaceTitle: name,
                                            onCreate: coll =>
                                                addCollection(coll, id),
                                        })
                                    }
                                    className={'c-action'}
                                    aria-label="add-child"
                                >
                                    <CreateNewFolderIcon />
                                </IconButton>
                            )}
                            {capabilities.canEdit && (
                                <IconButton
                                    color={'inherit'}
                                    component={ModalLink}
                                    route={modalRoutes.workspaces.routes.manage}
                                    params={{
                                        id,
                                        tab: 'edit',
                                    }}
                                    title={t(
                                        'workspace.item.edit',
                                        'Edit this workspace'
                                    )}
                                    className={'c-action'}
                                    aria-label="edit"
                                >
                                    <EditIcon />
                                </IconButton>
                            )}
                            <IconButton
                                color={'inherit'}
                                onClick={expandClick}
                                aria-label="expand-toggle"
                            >
                                {pager.expanding ? (
                                    <CircularProgress size={24} />
                                ) : !expanded ? (
                                    <ExpandLessIcon />
                                ) : (
                                    <ExpandMoreIcon />
                                )}
                            </IconButton>
                        </>
                    }
                    disablePadding
                >
                    <ListItemButton
                        sx={{
                            '&.Mui-selected': {
                                bgcolor: 'secondary.main',
                            },
                        }}
                        onClick={onClick}
                        selected={selected}
                    >
                        <ListItemIcon sx={{color: 'inherit'}}>
                            <BusinessIcon />
                        </ListItemIcon>
                        <ListItemText primary={name} />
                    </ListItemButton>
                </ListItem>
            </ListSubheader>

            <Collapse
                in={expanded && pager && pager.items.length > 0}
                timeout="auto"
                unmountOnExit
            >
                {pager?.items &&
                    pager!.items.map(c => (
                        <CollectionMenuItem
                            data={c}
                            workspaceId={id}
                            key={c.id}
                            absolutePath={c.id}
                            level={0}
                        />
                    ))}
                {pager && pager.items.length < (pager.total ?? 0) && (
                    <LoadMoreCollections
                        onLoadMore={() => loadMore(id)}
                        loading={pager.loadingMore}
                    />
                )}
            </Collapse>
        </>
    );
}
