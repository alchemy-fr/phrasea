import React, {MouseEvent, useContext, useState} from "react";
import {Collection, Workspace} from "../../types";
import CollectionMenuItem from "./CollectionMenuItem";
import {collectionChildrenLimit, collectionSecondLimit, getCollections} from "../../api/collection";
import {SearchFiltersContext} from "./Search/SearchFiltersContext";
import {Collapse, IconButton, ListItem, ListItemButton, ListItemIcon, ListItemText, ListSubheader} from "@mui/material";
import ExpandLessIcon from '@mui/icons-material/ExpandLess';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import EditIcon from '@mui/icons-material/Edit';
import CreateNewFolderIcon from '@mui/icons-material/CreateNewFolder';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import BusinessIcon from '@mui/icons-material/Business';
import {useModals} from "@mattjennings/react-modal-stack";
import CreateCollection from "./Collection/CreateCollection";
import {OnCollectionEdit} from "./Collection/EditCollection";
import EditWorkspace, {OnWorkspaceEdit} from "./Workspace/EditWorkspace";

export type WorkspaceMenuItemProps = {} & Workspace;

export default function WorkspaceMenuItem({
                                              id,
                                              name: initialName,
                                              collections,
                                              capabilities,
                                          }: WorkspaceMenuItemProps) {
    const filterContext = useContext(SearchFiltersContext);
    const {openModal} = useModals();
    const selected = filterContext.selectedWorkspace === id;
    const [expanded, setExpanded] = useState(false);
    const [name, setName] = useState(initialName);
    const [nextCollections, setNextCollections] = useState<{
        loadingMore: boolean,
        items: Collection[],
        total?: number
    }>({
        loadingMore: false,
        items: collections,
    });

    const expand = (force?: boolean) => {
        setExpanded(p => (!p || true === force));
    }
    const expandClick = (e: MouseEvent) => {
        e.stopPropagation();
        expand();
    }

    function getNextPage(): number | undefined {
        if (collections.length >= collectionChildrenLimit) {
            if (nextCollections.total) {
                if (nextCollections.items.length < nextCollections.total) {
                    return Math.floor(nextCollections.items.length / collectionSecondLimit) + 1;
                }
            } else {
                return 1;
            }
        }
    }

    const nextPage = getNextPage();

    const onClick = () => {
        filterContext.selectWorkspace(id, filterContext.selectedWorkspace === id);
        expand(true);
    };

    const loadMore = async (e: MouseEvent): Promise<void> => {
        setNextCollections(prevState => ({
            ...prevState,
            loadingMore: true,
        }));
        const  page = getNextPage();

        const items = await getCollections({
            workspaces: [id],
            page,
            limit: collectionSecondLimit,
            childrenLimit: collectionChildrenLimit,
        });

        setNextCollections(prevState => ({
            loadingMore: false,
            total: items.total,
            items: (page ?? 0) > 1 ? (prevState.items || []).concat(items.result) : items.result,
        }));
    }

    const onSubCollEdit: OnCollectionEdit = (item) => {
        setNextCollections(prevState => ({
            ...prevState,
            items: prevState.items?.map(i => i.id === item.id ? item : i),
        }));
    };

    const onSubCollDelete = (id: string) => {
        setNextCollections(prevState => ({
            ...prevState,
            total: (prevState.total ?? 1) - 1,
            items: prevState.items?.filter(i => i.id !== id),
        }));
    };

    const onCollectionCreate: OnCollectionEdit = (item) => {
        setNextCollections(prevState => ({
            ...prevState,
            total: (prevState.total ?? 0) + 1,
            items: (prevState.items || []).concat(item),
        }));
        setExpanded(true);
    }

    const onWorkspaceEdit: OnWorkspaceEdit = (item) => {
        setName(item.name);
    }

    return <>
        <ListSubheader
            component={'div'}
            disableGutters={true}
            className={'workspace-item'}
        >
            <ListItem
                sx={{
                    backgroundColor: 'primary.main',
                    color: 'primary.contrastText',
                    '*': {
                        color: 'inherit',
                    },
                    '.c-action': {
                        visibility: 'hidden',
                    },
                    '&:hover .c-action': {
                        visibility: 'visible',
                    }
                }}
                secondaryAction={<>
                    {capabilities.canEdit && <IconButton
                        color={'inherit'}
                        title={'Add collection in this workspace'}
                        onClick={() => openModal(CreateCollection, {
                            workspaceId: id,
                            workspaceTitle: name,
                            onCreate: onCollectionCreate,
                        })}
                        className={'c-action'}
                        aria-label="add-child">
                        <CreateNewFolderIcon/>
                    </IconButton>}
                    {capabilities.canEdit && <IconButton
                        color={'inherit'}
                        onClick={() => openModal(EditWorkspace, {
                            id,
                            onEdit: onWorkspaceEdit,
                        })}
                        title={'Edit this workspace'}
                        className={'c-action'}
                        aria-label="edit">
                        <EditIcon/>
                    </IconButton>}
                    <IconButton
                        color={'inherit'}
                        onClick={expandClick}
                        aria-label="expand-toggle">
                        {!expanded ? <ExpandLessIcon/> : <ExpandMoreIcon/>}
                    </IconButton>
                </>}
                disablePadding
            >
                <ListItemButton
                    role={undefined}
                    onClick={onClick}
                    selected={selected}
                >
                    <ListItemIcon>
                        <BusinessIcon/>
                    </ListItemIcon>
                    <ListItemText primary={name}/>
                </ListItemButton>
            </ListItem>
        </ListSubheader>
        <Collapse in={expanded && nextCollections.items.length > 0} timeout="auto" unmountOnExit>
            {nextCollections.items && nextCollections.items.map(c => <CollectionMenuItem
                {...c}
                onCollectionEdit={onSubCollEdit}
                onCollectionDelete={() => onSubCollDelete(c.id)}
                key={c.id}
                absolutePath={c.id}
                level={0}
            />)}
            {expanded && Boolean(nextPage) && <ListItemButton
                onClick={loadMore}
                disabled={nextCollections.loadingMore}
            >
                <MoreHorizIcon/>
                Load more collections
            </ListItemButton>}
        </Collapse>
    </>
}
