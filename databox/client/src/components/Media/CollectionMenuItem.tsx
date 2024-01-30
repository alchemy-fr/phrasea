import React, {MouseEvent, useContext, useState} from 'react';
import {Collection} from '../../types';
import {SearchContext} from './Search/SearchContext';
import {
    CircularProgress,
    Collapse,
    IconButton,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
} from '@mui/material';
import FolderIcon from '@mui/icons-material/Folder';
import FolderOutlinedIcon from '@mui/icons-material/FolderOutlined';
import FolderSharedIcon from '@mui/icons-material/FolderShared';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import ExpandLessIcon from '@mui/icons-material/ExpandLess';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import CreateNewFolderIcon from '@mui/icons-material/CreateNewFolder';
import AddPhotoAlternateIcon from '@mui/icons-material/AddPhotoAlternate';
import CreateCollection from './Collection/CreateCollection';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import ModalLink from '../Routing/ModalLink';
import ConfirmDialog from '../Ui/ConfirmDialog';
import {useModals} from '@alchemy/navigation';
import UploadModal from '../Upload/UploadModal';
import {modalRoutes} from '../../routes.ts';
import {useAuth} from '@alchemy/react-auth';
import {CollectionPager, useCollectionStore} from "../../store/collectionStore.ts";
import {deleteCollection} from '../../api/collection';

type Props = {
    level: number;
    workspaceId: string;
    absolutePath: string;
    titlePath?: string[];
    data: Collection;
};

export default function CollectionMenuItem({
    data,
    absolutePath,
    titlePath,
    level,
    workspaceId,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const searchContext = useContext(SearchContext);
    const authContext = useAuth();
    const [[expanded, clicked], setExpanded] = useState<[boolean, boolean]>([false, false]);
    const childCount = data.children?.length ?? 0;

    const loadChildren = useCollectionStore((state) => state.loadChildren);
    const addCollection = useCollectionStore((state) => state.addCollection);
    useCollectionStore((state) => state.collections); // Subscribe to collection updates

    const pager =
        useCollectionStore((state) => state.tree)[data.id]
        ?? {
            items: data.children,
            expanding: false,
            loadingMore: false,
        } as CollectionPager;

    const {workspace} = data;

    React.useEffect(() => {
        if (expanded && clicked) {
            (async () => {
                if (childCount > 0) {
                    loadChildren(workspaceId, data.id)
                }
            })();
        }
    }, [expanded, clicked]);

    const expand = (force?: boolean, clicked?: boolean) => {
        setExpanded(p => [!p[0] || true === force, clicked || p[1]]);
    };
    const expandClick = (e: MouseEvent) => {
        e.stopPropagation();
        expand(false, true);
    };

    const onDelete = (e: MouseEvent): void => {
        e.stopPropagation();

        openModal(ConfirmDialog, {
            textToType: data.title,
            title: t(
                'collection_delete.title.confirm',
                'Are you sure you want to delete this collection?'
            ),
            onConfirm: async () => {
                await deleteCollection(data.id);
                toast.success(
                    t(
                        'delete.collection.confirmed',
                        'Collection has been removed!'
                    ) as string
                );
            },
        });
    };

    const selected = searchContext.collections.includes('/' + absolutePath);
    const expanding = false; // TODO
    const onClick = () => {
        searchContext.selectCollection(
            absolutePath,
            (titlePath ?? []).concat(data.title).join(` / `),
            selected
        );
        expand(true, true);
    };

    const currentInSelectedHierarchy = searchContext.collections.some(c =>
        c.startsWith('/' + absolutePath)
    );

    return (
        <>
            <ListItem
                sx={{
                    '.c-action': {
                        visibility: 'hidden',
                        bgcolor: 'inherit',
                    },
                    '&:hover .c-action': {
                        visibility: 'visible',
                    },
                }}
                secondaryAction={
                    <>
                        <span className="c-action">
                            {data.capabilities.canEdit && (
                                <IconButton
                                    title={t(
                                        'collection.item.create_asset',
                                        'Add new asset to collection'
                                    )}
                                    onClick={() =>
                                        openModal(UploadModal, {
                                            files: [],
                                            userId: authContext!.user!.id,
                                            workspaceTitle: workspace.name,
                                            workspaceId: workspace.id,
                                            collectionId: data.id,
                                            titlePath: (titlePath ?? []).concat(
                                                data.title
                                            ),
                                        })
                                    }
                                    aria-label="create-asset"
                                >
                                    <AddPhotoAlternateIcon/>
                                </IconButton>
                            )}
                            {data.capabilities.canEdit && (
                                <IconButton
                                    title={t(
                                        'collection.item.create_collection',
                                        'Create new collection in this one'
                                    )}
                                    onClick={() =>
                                        openModal(CreateCollection, {
                                            parent: data['@id'],
                                            workspaceTitle: workspace.name,
                                            titlePath: (titlePath ?? []).concat(
                                                data.title
                                            ),
                                            onCreate: (coll) => {
                                                addCollection(coll, workspaceId, data.id);
                                                expand(true);
                                            },
                                        })
                                    }
                                    aria-label="add-child"
                                >
                                    <CreateNewFolderIcon/>
                                </IconButton>
                            )}
                            {data.capabilities.canEdit && (
                                <IconButton
                                    component={ModalLink}
                                    route={
                                        modalRoutes.collections.routes.manage
                                    }
                                    params={{
                                        id: data.id,
                                        tab: 'edit',
                                    }}
                                    title={t(
                                        'collection.item.edit',
                                        'Edit this collection'
                                    )}
                                    aria-label="edit"
                                >
                                    <EditIcon/>
                                </IconButton>
                            )}
                            {data.capabilities.canDelete && (
                                <IconButton
                                    onClick={onDelete}
                                    aria-label="delete"
                                >
                                    <DeleteIcon/>
                                </IconButton>
                            )}
                        </span>
                        <IconButton
                            style={{
                                visibility:
                                    childCount > 0 ? 'visible' : 'hidden',
                            }}
                            onClick={expandClick}
                            aria-label="expand-toggle"
                        >
                            {expanding ? (
                                <CircularProgress size={24}/>
                            ) : !expanded ? (
                                <ExpandLessIcon/>
                            ) : (
                                <ExpandMoreIcon/>
                            )}
                        </IconButton>
                    </>
                }
                disablePadding
            >
                <ListItemButton
                    selected={Boolean(selected || currentInSelectedHierarchy)}
                    role={undefined}
                    onClick={onClick}
                    style={{paddingLeft: `${10 + level * 10}px`}}
                >
                    <ListItemIcon
                        sx={{
                            minWidth: 35,
                        }}
                    >
                        {data.public ? <FolderOutlinedIcon/> : (data.shared ? <FolderSharedIcon/> : <FolderIcon/>)}
                    </ListItemIcon>
                    <ListItemText primary={data.title}/>
                </ListItemButton>
            </ListItem>

            <Collapse
                in={expanded && childCount > 0}
                timeout="auto"
                unmountOnExit
            >
                {childCount > 0 && (
                    <div className="sub-colls">
                        {pager?.items.map(c => {
                            return (
                                <CollectionMenuItem
                                    data={c}
                                    workspaceId={workspaceId}
                                    key={`${c.id}-${c.children ? 'c' : ''}`}
                                    absolutePath={`${absolutePath}/${c.id}`}
                                    titlePath={(titlePath ?? []).concat(data.title)}
                                    level={level + 1}
                                />
                            );
                        })}
                        {(pager && pager.items.length < (pager.total ?? 0)) && (
                            <ListItemButton
                                // onClick={loadMore}
                                // disabled={nextCollections.loadingMore}
                            >
                                <MoreHorizIcon/>
                                Load more collections
                            </ListItemButton>
                        )}
                    </div>
                )}
            </Collapse>
        </>
    );
}
