import {MouseEvent, useContext, useEffect, useState} from 'react';
import {Collection, CollectionOptionalWorkspace} from '../../types';
import {
    collectionChildrenLimit,
    collectionSecondLimit,
    deleteCollection,
    getCollections,
} from '../../api/collection';
import {SearchContext} from './Search/SearchContext';
import {
    CircularProgress,
    Collapse,
    IconButton,
    ListItem,
    ListItemButton,
    ListItemText,
} from '@mui/material';
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
import {OnCollectionEdit} from '../Dialog/Collection/EditCollection';
import UploadModal from '../Upload/UploadModal';
import {modalRoutes} from '../../routes.ts';
import {useKeycloakUser as useUser} from '@alchemy/auth';

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
    onCollectionDelete,
    workspace,
    level,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const searchContext = useContext(SearchContext);
    const userContext = useUser();
    const [expanded, setExpanded] = useState(false);
    const [expanding, setExpanding] = useState(false);
    const [nextCollections, setNextCollections] = useState<{
        loadingMore: boolean;
        items?: CollectionOptionalWorkspace[];
        total?: number;
        page: number;
    }>({
        page: 0,
        loadingMore: false,
        items: children,
    });

    const childCount = nextCollections.items?.length ?? 0;

    useEffect(() => {
        if (expanded) {
            (async () => {
                if (expanded && childCount > 0) {
                    const timeout = setTimeout(() => {
                        setExpanding(true);
                    }, 800);

                    const data = await getCollections({
                        parent: id,
                        limit: collectionSecondLimit,
                        childrenLimit: collectionChildrenLimit,
                    });
                    clearTimeout(timeout);
                    setNextCollections(prevState => ({
                        loadingMore: false,
                        page: 1,
                        total:
                            prevState.page < 1
                                ? (prevState.total ?? 0) + data.total
                                : data.total,
                        items:
                            prevState.page < 1
                                ? (
                                      data.result as CollectionOptionalWorkspace[]
                                  ).concat(
                                      (prevState.items || []).filter(
                                          pc =>
                                              !data.result.some(
                                                  c => c.id === pc.id
                                              )
                                      )
                                  )
                                : data.result,
                    }));

                    setExpanding(false);
                }
            })();
        }
    }, [expanded]);

    const expand = (force?: boolean) => {
        setExpanded(p => !p || true === force);
    };
    const expandClick = (e: MouseEvent) => {
        e.stopPropagation();
        expand();
    };

    const onDelete = (e: MouseEvent): void => {
        e.stopPropagation();

        openModal(ConfirmDialog, {
            textToType: title,
            title: t(
                'collection_delete.title.confirm',
                'Are you sure you want to delete this collection?'
            ),
            onConfirm: async () => {
                await deleteCollection(id);
                onCollectionDelete();
                toast.success(
                    t(
                        'delete.collection.confirmed',
                        'Collection has been removed!'
                    ) as string
                );
            },
        });
    };

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

    const selected = searchContext.collections.includes('/' + absolutePath);
    const onClick = () => {
        searchContext.selectCollection(
            absolutePath,
            (titlePath ?? []).concat(title).join(` / `),
            selected
        );
        expand(true);
    };

    const loadMore = async (): Promise<void> => {
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
    };

    const onSubCollEdit: OnCollectionEdit = item => {
        setNextCollections(prevState => ({
            ...prevState,
            total: nextCollections.total,
            items: prevState.items?.map(i => (i.id === item.id ? item : i)),
        }));
    };

    const onSubCollDelete = (id: string) => {
        setNextCollections(prevState => ({
            ...prevState,
            total: nextCollections.total,
            items: prevState.items?.filter(i => i.id !== id),
        }));
    };

    const onCollectionCreate: OnCollectionEdit = item => {
        setNextCollections(prevState => ({
            ...prevState,
            total: (prevState.total ?? 0) + 1,
            items: (prevState.items || []).concat(item),
        }));
        setExpanded(true);
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
                            {capabilities.canEdit && (
                                <IconButton
                                    title={t(
                                        'collection.item.create_asset',
                                        'Add new asset to collection'
                                    )}
                                    onClick={() =>
                                        openModal(UploadModal, {
                                            files: [],
                                            userId: userContext!.user!.id,
                                            workspaceTitle: workspace.name,
                                            workspaceId: workspace.id,
                                            collectionId: id,
                                            titlePath: (titlePath ?? []).concat(
                                                title
                                            ),
                                        })
                                    }
                                    aria-label="create-asset"
                                >
                                    <AddPhotoAlternateIcon />
                                </IconButton>
                            )}
                            {capabilities.canEdit && (
                                <IconButton
                                    title={t(
                                        'collection.item.create_collection',
                                        'Create new collection in this one'
                                    )}
                                    onClick={() =>
                                        openModal(CreateCollection, {
                                            parent: iri,
                                            workspaceTitle: workspace.name,
                                            titlePath: (titlePath ?? []).concat(
                                                title
                                            ),
                                            onCreate: onCollectionCreate,
                                        })
                                    }
                                    aria-label="add-child"
                                >
                                    <CreateNewFolderIcon />
                                </IconButton>
                            )}
                            {capabilities.canEdit && (
                                <IconButton
                                    component={ModalLink}
                                    route={
                                        modalRoutes.collections.routes.manage
                                    }
                                    params={{
                                        id,
                                        tab: 'edit',
                                    }}
                                    title={t(
                                        'collection.item.edit',
                                        'Edit this collection'
                                    )}
                                    aria-label="edit"
                                >
                                    <EditIcon />
                                </IconButton>
                            )}
                            {capabilities.canDelete && (
                                <IconButton
                                    onClick={onDelete}
                                    aria-label="delete"
                                >
                                    <DeleteIcon />
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
                    selected={Boolean(selected || currentInSelectedHierarchy)}
                    role={undefined}
                    onClick={onClick}
                    style={{paddingLeft: `${10 + level * 10}px`}}
                >
                    <ListItemText primary={title} />
                </ListItemButton>
            </ListItem>

            <Collapse
                in={expanded && childCount > 0}
                timeout="auto"
                unmountOnExit
            >
                {childCount > 0 && (
                    <div className="sub-colls">
                        {nextCollections.items!.map(c => {
                            return (
                                <CollectionMenuItem
                                    {...c}
                                    workspace={c.workspace || workspace}
                                    onCollectionEdit={onSubCollEdit}
                                    onCollectionDelete={() =>
                                        onSubCollDelete(c.id)
                                    }
                                    key={`${c.id}-${c.children ? 'c' : ''}`}
                                    absolutePath={`${absolutePath}/${c.id}`}
                                    titlePath={(titlePath ?? []).concat(title)}
                                    level={level + 1}
                                />
                            );
                        })}
                        {Boolean(nextPage) && (
                            <ListItemButton
                                onClick={loadMore}
                                disabled={nextCollections.loadingMore}
                            >
                                <MoreHorizIcon />
                                Load more collections
                            </ListItemButton>
                        )}
                    </div>
                )}
            </Collapse>
        </>
    );
}
