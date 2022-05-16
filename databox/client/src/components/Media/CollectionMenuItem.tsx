import React, {MouseEvent, useContext, useEffect, useState} from "react";
import {Collection} from "../../types";
import {collectionChildrenLimit, collectionSecondLimit, deleteCollection, getCollections} from "../../api/collection";
import apiClient from "../../api/api-client";
import {SearchContext} from "./Search/SearchContext";
import {Collapse, IconButton, ListItem, ListItemButton, ListItemText} from "@mui/material";
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import ExpandLessIcon from "@mui/icons-material/ExpandLess";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import EditIcon from "@mui/icons-material/Edit";
import DeleteIcon from "@mui/icons-material/Delete";
import CreateNewFolderIcon from "@mui/icons-material/CreateNewFolder";
import AddPhotoAlternateIcon from "@mui/icons-material/AddPhotoAlternate";
import EditCollection, {OnCollectionEdit} from "./Collection/EditCollection";
import {useModals} from "@mattjennings/react-modal-stack";
import CreateCollection from "./Collection/CreateCollection";
import {toast} from "react-toastify";
import {useTranslation} from "react-i18next";
import CreateAsset from "./Asset/CreateAsset";

type Props = {
    level: number;
    absolutePath: string;
    titlePath?: string[];
    onCollectionEdit: OnCollectionEdit;
    onCollectionDelete: () => void;
} & Collection;

export default function CollectionMenuItem({
                                               id,
                                               ['@id']: iri,
                                               children,
                                               absolutePath,
                                                titlePath,
                                               title,
                                               capabilities,
                                               onCollectionEdit,
                                               onCollectionDelete,
    workspace,
                                               level,
                                           }: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const searchContext = useContext(SearchContext);
    const [expanded, setExpanded] = useState(false);
    const [nextCollections, setNextCollections] = useState<{
        loadingMore: boolean;
        items?: Collection[];
        total?: number;
        page: number;
    }>({
        page: 0,
        loadingMore: false,
        items: children,
    });

    const childCount = nextCollections.items?.length ?? 0;

    const loadChildren = async () => {
        if (expanded && childCount > 0) {
            const data = (await getCollections({
                parent: id,
                limit: collectionSecondLimit,
                childrenLimit: collectionChildrenLimit,
            }));

            setNextCollections(prevState => ({
                loadingMore: false,
                page: 1,
                total: prevState.page < 1 ? (prevState.total ?? 0) + data.total : data.total,
                items: prevState.page < 1 ? data.result.filter(c => !(prevState.items || []).some(pc => pc.id === c.id)).concat(
                    (prevState.items || [])
                ) : data.result,
            }));
        }
    };

    useEffect(() => {
        if (expanded) {
            loadChildren();
        }
    }, [expanded]);

    const expand = (force?: boolean) => {
        setExpanded(p => (!p || true === force));
    }
    const expandClick = (e: MouseEvent) => {
        e.stopPropagation();
        expand();
    }

    const onDelete = (e: MouseEvent): void => {
        e.stopPropagation();
        if (window.confirm(t('delete.collection.confirm', 'Delete? Really?'))) {
            deleteCollection(id).then(() => {
                toast.success(t('delete.collection.confirmed', 'Collection has been removed!'));
            });
            onCollectionDelete();
        }
    }

    function getNextPage(): number | undefined {
        if (childCount >= collectionChildrenLimit) {
            if (nextCollections.items) {
                if (childCount < nextCollections.total!) {
                    return Math.floor(childCount / collectionSecondLimit) + 1;
                }
            } else {
                return 1;
            }
        }
    }

    const nextPage = getNextPage();

    const onClick = () => {
        searchContext.selectCollection(absolutePath, searchContext.collectionId === absolutePath);
        expand(true);
    };

    const loadMore = async (e: MouseEvent): Promise<void> => {
        setNextCollections(prevState => ({
            ...prevState,
            loadingMore: true,
        }));

        const page = getNextPage();
        const items = await getCollections({
            parent: id,
            page,
            limit: collectionSecondLimit,
            childrenLimit: collectionChildrenLimit,
        });

        setNextCollections(prevState => ({
            loadingMore: false,
            page: page ?? 1,
            total: nextCollections.total,
            items: (prevState.items || []).concat(items.result),
        }));
    }

    const onSubCollEdit: OnCollectionEdit = (item) => {
        setNextCollections(prevState => ({
            ...prevState,
            total: nextCollections.total,
            items: prevState.items?.map(i => i.id === item.id ? item : i),
        }));
    };

    const onSubCollDelete = (id: string) => {
        setNextCollections(prevState => ({
            ...prevState,
            total: nextCollections.total,
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
    };

    const selected = searchContext.collectionId === absolutePath;
    const currentInSelectedHierarchy = searchContext.collectionId && searchContext.collectionId.startsWith(absolutePath);

    return <>
        <ListItem
            sx={{
                '.c-action': {
                    visibility: 'hidden',
                },
                '&:hover .c-action': {
                    visibility: 'visible',
                }
            }}
            secondaryAction={<>
                {capabilities.canEdit && <IconButton
                    className={'c-action'}
                    title={'Add new asset to collection'}
                    onClick={() => openModal(CreateAsset, {
                        collectionId: id,
                        workspaceTitle: workspace.name,
                        titlePath: (titlePath ?? []).concat(title),
                    })}
                    aria-label="create-asset">
                    <AddPhotoAlternateIcon/>
                </IconButton>}
                {capabilities.canEdit && <IconButton
                    className={'c-action'}
                    title={'Create new collection in this one'}
                    onClick={() => openModal(CreateCollection, {
                        parent: iri,
                        workspaceTitle: workspace.name,
                        titlePath: (titlePath ?? []).concat(title),
                        onCreate: onCollectionCreate,
                    })}
                    aria-label="add-child">
                    <CreateNewFolderIcon/>
                </IconButton>}
                {capabilities.canEdit && <IconButton
                    title={'Edit this collection'}
                    onClick={() => openModal(EditCollection, {
                        id,
                        onEdit: onCollectionEdit,
                    })}
                    className={'c-action'}
                    aria-label="edit">
                    <EditIcon/>
                </IconButton>}
                {capabilities.canDelete && <IconButton
                    onClick={onDelete}
                    className={'c-action'}
                    aria-label="delete">
                    <DeleteIcon/>
                </IconButton>}
                <IconButton
                    style={{
                        visibility: childCount > 0 ? 'visible' : 'hidden'
                    }}
                    onClick={expandClick}
                    aria-label="expand-toggle">
                    {!expanded ? <ExpandLessIcon/> : <ExpandMoreIcon/>}
                </IconButton>
            </>}
            disablePadding
        >
            <ListItemButton
                selected={Boolean(selected || currentInSelectedHierarchy)}
                role={undefined}
                onClick={onClick}
                style={{paddingLeft: `${10 + level * 10}px`}}
            >
                <ListItemText primary={title}/>
            </ListItemButton>
        </ListItem>

        <Collapse in={expanded && childCount > 0} timeout="auto" unmountOnExit>
            {childCount > 0 && <div className="sub-colls">
                {nextCollections.items!.map(c => <CollectionMenuItem
                    {...c}
                    onCollectionEdit={onSubCollEdit}
                    onCollectionDelete={() => onSubCollDelete(c.id)}
                    key={c.id}
                    absolutePath={`${absolutePath}/${c.id}`}
                    titlePath={(titlePath ?? []).concat(title)}
                    level={level + 1}
                />)}
                {Boolean(nextPage) && <ListItemButton
                    onClick={loadMore}
                    disabled={nextCollections.loadingMore}
                >
                    <MoreHorizIcon/>
                    Load more collections
                </ListItemButton>}
            </div>}
        </Collapse>
    </>
}
