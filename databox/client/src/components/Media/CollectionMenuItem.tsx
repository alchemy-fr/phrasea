import React, {MouseEvent, useContext, useState} from 'react';
import {Collection, Workspace} from '../../types';
import {SearchContext} from './Search/SearchContext';
import {
    CircularProgress,
    Collapse,
    Divider,
    IconButton,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    MenuItem,
} from '@mui/material';
import FolderIcon from '@mui/icons-material/Folder';
import FolderOutlinedIcon from '@mui/icons-material/FolderOutlined';
import FolderSharedIcon from '@mui/icons-material/FolderShared';
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
import {modalRoutes} from '../../routes';
import {useAuth} from '@alchemy/react-auth';
import {CollectionPager, useCollectionStore} from '../../store/collectionStore';
import {deleteCollection} from '../../api/collection';
import LoadMoreCollections from './Collection/LoadMoreCollections';
import {MoreActionsButton} from '@alchemy/phrasea-ui';
import {cActionClassName} from './WorkspaceMenuItem';
import NotificationsIcon from '@mui/icons-material/Notifications';

type Props = {
    level: number;
    absolutePath: string;
    titlePath?: string[];
    collection: Collection;
    workspace: Workspace;
};

export const collectionItemClassName = 'collection-item';

export default function CollectionMenuItem({
    collection,
    absolutePath,
    titlePath,
    level,
    workspace,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const searchContext = useContext(SearchContext)!;
    const authContext = useAuth();
    const [expanded, setExpanded] = useState<boolean>(false);
    const [childrenLoaded, setChildrenLoaded] = React.useState(false);
    const childCount = collection.children?.length ?? 0;

    const load = useCollectionStore(state => state.load);
    const addCollection = useCollectionStore(state => state.addCollection);
    const loadMore = useCollectionStore(state => state.loadMore);
    useCollectionStore(state => state.collections); // Subscribe to collection updates

    const pager =
        useCollectionStore(state => state.tree)[collection.id] ??
        ({
            items: collection.children,
            expanding: false,
            loadingMore: false,
        } as CollectionPager);

    React.useEffect(() => {
        if (expanded && !childrenLoaded && childCount > 0) {
            load(workspace.id, collection.id).then(() => {
                setChildrenLoaded(true);
            });
        }
    }, [expanded, childrenLoaded]);

    const expand = (force?: boolean) => {
        setExpanded(p => !p || !!force);
    };
    const expandClick = (e: MouseEvent) => {
        e.stopPropagation();
        expand();

        if (e.detail > 1) {
            // is double click
            load(workspace.id, collection.id, true);
        }
    };

    const onDelete = (e: MouseEvent): void => {
        e.stopPropagation();

        openModal(ConfirmDialog, {
            textToType: collection.title,
            title: t(
                'collection_delete.confirm',
                'Are you sure you want to delete this collection?'
            ),
            onConfirm: async () => {
                await deleteCollection(collection.id);
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
    const onClick = () => {
        searchContext.selectCollection(
            collection.id,
            (titlePath ?? []).concat(collection.title).join(` / `),
            selected
        );
        expand(true);
    };

    const currentInSelectedHierarchy = searchContext.collections.some(c =>
        c.startsWith('/' + absolutePath)
    );

    return (
        <>
            <ListItem
                className={collectionItemClassName}
                secondaryAction={
                    <span className={cActionClassName}>
                        <MoreActionsButton
                            disablePortal={false}
                            anchorOrigin={{
                                vertical: 'bottom',
                                horizontal: 'left',
                            }}
                        >
                            {closeWrapper => [
                                <MenuItem
                                    key='notifications'
                                    onClick={closeWrapper()}
                                    component={ModalLink}
                                    route={
                                        modalRoutes.collections.routes.manage
                                    }
                                    params={{
                                        id: collection.id,
                                        tab: 'notifications',
                                    }}
                                    aria-label="notifications"
                                >
                                    <ListItemIcon>
                                        <NotificationsIcon />
                                    </ListItemIcon>
                                    <ListItemText
                                        primary={t(
                                            'collection.item.notifications',
                                            'Notifications'
                                        )}
                                    />
                                </MenuItem>,
                                collection.capabilities.canEdit ? <Divider key='divider1' /> : null,
                                collection.capabilities.canEdit &&
                                authContext!.isAuthenticated() ? (
                                    <MenuItem
                                        key='create-asset'
                                        onClick={closeWrapper(() =>
                                            openModal(UploadModal, {
                                                files: [],
                                                workspaceTitle: workspace.name,
                                                workspaceId: workspace.id,
                                                collectionId: collection.id,
                                                titlePath: (
                                                    titlePath ?? []
                                                ).concat(collection.title),
                                            })
                                        )}
                                        aria-label="create-asset"
                                    >
                                        <ListItemIcon>
                                            <AddPhotoAlternateIcon />
                                        </ListItemIcon>
                                        <ListItemText
                                            primary={t(
                                                'collection.item.create_asset',
                                                'Add Asset to Collection'
                                            )}
                                        />
                                    </MenuItem>
                                ) : null,
                                collection.capabilities.canEdit ? (
                                    <MenuItem
                                        key='create-collection'
                                        onClick={closeWrapper(() =>
                                            openModal(CreateCollection, {
                                                parent: collection['@id'],
                                                workspaceTitle: workspace.name,
                                                titlePath: (
                                                    titlePath ?? []
                                                ).concat(collection.title),
                                                onCreate: coll => {
                                                    addCollection(
                                                        coll,
                                                        workspace.id,
                                                        collection.id
                                                    );
                                                    expand(true);
                                                },
                                            })
                                        )}
                                        aria-label="add-child"
                                    >
                                        <ListItemIcon>
                                            <CreateNewFolderIcon />
                                        </ListItemIcon>
                                        <ListItemText
                                            primary={t(
                                                'collection.item.create_collection',
                                                'Create sub collection'
                                            )}
                                        />
                                    </MenuItem>
                                ) : null,
                                collection.capabilities.canEdit || collection.capabilities.canDelete ? <Divider key='divider2' /> : null,
                                collection.capabilities.canEdit ? (
                                    <MenuItem
                                        key='edit'
                                        onClick={closeWrapper()}
                                        component={ModalLink}
                                        route={
                                            modalRoutes.collections.routes
                                                .manage
                                        }
                                        params={{
                                            id: collection.id,
                                            tab: 'edit',
                                        }}
                                        aria-label="edit"
                                    >
                                        <ListItemIcon>
                                            <EditIcon />
                                        </ListItemIcon>
                                        <ListItemText
                                            primary={t(
                                                'collection.item.edit',
                                                'Edit'
                                            )}
                                        />
                                    </MenuItem>
                                ) : null,
                                collection.capabilities.canDelete ? (
                                    <MenuItem
                                        key='delete'
                                        onClick={closeWrapper(onDelete)}
                                        aria-label="delete"
                                    >
                                        <ListItemIcon>
                                            <DeleteIcon color={'error'} />
                                        </ListItemIcon>
                                        <ListItemText
                                            primary={t(
                                                'collection.item.delete',
                                                'Delete'
                                            )}
                                        />
                                    </MenuItem>
                                ) : null,
                            ]}
                        </MoreActionsButton>
                        <IconButton
                            style={{
                                visibility:
                                    childCount > 0 ? 'visible' : 'hidden',
                            }}
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
                    </span>
                }
                disablePadding
            >
                <ListItemButton
                    selected={Boolean(selected || currentInSelectedHierarchy)}
                    role={undefined}
                    onClick={onClick}
                    style={{paddingLeft: `${10 + level * 10}px`}}
                >
                    <ListItemIcon>
                        {collection.public ? (
                            <FolderOutlinedIcon />
                        ) : collection.shared ? (
                            <FolderSharedIcon />
                        ) : (
                            <FolderIcon />
                        )}
                    </ListItemIcon>
                    <ListItemText primary={collection.title} />
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
                                    collection={c}
                                    workspace={workspace}
                                    key={`${c.id}-${c.children ? 'c' : ''}`}
                                    absolutePath={`${absolutePath}/${c.id}`}
                                    titlePath={(titlePath ?? []).concat(
                                        collection.title
                                    )}
                                    level={level + 1}
                                />
                            );
                        })}
                        {pager && pager.items.length < (pager.total ?? 0) && (
                            <LoadMoreCollections
                                onLoadMore={() =>
                                    loadMore(workspace.id, collection.id)
                                }
                                loading={pager.loadingMore}
                            />
                        )}
                    </div>
                )}
            </Collapse>
        </>
    );
}
