import React, {useCallback} from "react";
import {CollectionPager, useCollectionStore} from "../../../../store/collectionStore.ts";
import EditableCollectionTree, {defaultNewCollectionName} from "../EditableTree.tsx";
import {TreeItem} from "@mui/x-tree-view";
import {IconButton, Stack} from "@mui/material";
import CreateNewFolderIcon from "@mui/icons-material/CreateNewFolder";
import {CollectionOptionalWorkspace} from "../../../../types.ts";
import {CommonTreeItemProps, treeViewPathSeparator} from "./collectionTree.ts";
import TreeItemLoader from "./TreeItemLoader.tsx";

type Props<IsMulti extends boolean = false> = {
    collection: CollectionOptionalWorkspace;
    depth?: number;
    workspaceId: string;
} & CommonTreeItemProps<IsMulti>;

export function CollectionTreeItem<IsMulti extends boolean = false>({
    updateCollectionPath,
    newCollectionPath,
    setNewCollectionPath,
    collection,
    workspaceId,
    disabledBranches,
    setExpanded,
    allowNew,
    isSelectable,
    depth = 0,
}: Props<IsMulti>) {
    const [loaded, setLoaded] = React.useState(false);
    const loadCollections = useCollectionStore(state => state.load);

    const pager =
        useCollectionStore(state => state.tree)[collection.id] ??
        ({
            items: collection.children,
            expanding: false,
            loadingMore: false,
        } as CollectionPager);

    async function load() {
        if (!collection.children || collection.children.length === 0) {
            return;
        }

        if (!loaded) {
            setLoaded(true);
            await loadCollections(workspaceId, collection.id);
        }
    }

    const collectionIRI = collection['@id'];
    const nodeId = workspaceId + treeViewPathSeparator + collectionIRI;
    const hasTree = pager.items && pager.items.length > 0;
    const hasNewCollectionPath =
        newCollectionPath && newCollectionPath.rootNode === nodeId;

    const onCreateNewCollection = useCallback(
        (e: React.MouseEvent<HTMLButtonElement>) => {
            e.stopPropagation();
            setNewCollectionPath(
                [
                    {
                        value: defaultNewCollectionName,
                        id: '0',
                        editing: true,
                    },
                ],
                nodeId
            );
            setExpanded(prev =>
                !prev.includes(nodeId) ? prev.concat(nodeId) : prev
            );
        },
        [setNewCollectionPath, setExpanded, nodeId]
    );

    return (
        <TreeItem
            disabled={
                (disabledBranches &&
                    disabledBranches.some(b => nodeId.startsWith(b))) ||
                (isSelectable && !isSelectable(collection))
            }
            onClick={load}
            nodeId={nodeId}
            label={
                <Stack direction={'row'} alignItems={'center'}>
                    {collection.title}
                    {allowNew && collection.capabilities.canEdit && (
                        <IconButton
                            sx={{ml: 1}}
                            onClick={onCreateNewCollection}
                        >
                            <CreateNewFolderIcon/>
                        </IconButton>
                    )}
                </Stack>
            }
        >
            {/*Wrapping all to avoid collapse in node */}
            {pager.expanding || hasTree || (allowNew && hasNewCollectionPath) ? (
                <>
                    {pager.expanding ? <TreeItemLoader/> : null}
                    {allowNew && hasNewCollectionPath ? (
                        <EditableCollectionTree
                            nodes={newCollectionPath!.nodes}
                            offset={0}
                            onEdit={updateCollectionPath}
                            setExpanded={setExpanded}
                        />
                    ) : null}
                    {hasTree &&
                        pager.items!.map(c => (
                            <CollectionTreeItem
                                key={c.id}
                                workspaceId={workspaceId}
                                collection={c}
                                depth={depth + 1}
                                newCollectionPath={
                                    newCollectionPath &&
                                    newCollectionPath.rootNode === collectionIRI
                                        ? undefined
                                        : newCollectionPath
                                }
                                setNewCollectionPath={setNewCollectionPath}
                                updateCollectionPath={updateCollectionPath}
                                disabledBranches={disabledBranches}
                                setExpanded={setExpanded}
                                allowNew={allowNew}
                            />
                        ))}
                </>
            ) : null}
        </TreeItem>
    );
}
