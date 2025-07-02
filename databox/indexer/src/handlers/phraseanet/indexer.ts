import {IndexIterator} from '../../indexers';
import {
    ConfigDataboxMapping,
    FieldMap,
    PhraseanetConfig,
    PhraseanetDatabox,
} from './types';
import {CPhraseanetRecord, CPhraseanetStory} from './CPhraseanetRecord';
import PhraseanetClient, {ORDER_ASC} from './phraseanetClient';
import {AttrPolicyIndex, createAsset, TagIndex} from './shared';
import {getConfig, getStrict} from '../../configLoader';
import {
    concatPath,
    escapePath,
    escapeSlashes,
    splitPath,
} from '../../lib/pathUtils';
import Twig from 'twig';
import {Logger} from 'winston';
import {DataboxClient} from '../../databox/client';
import p from 'path';
import {getAlternateUrls} from '../../alternateUrl';
import {
    importSubdefsStructure,
    importMetadataStructure,
    importStatusBitsStructure,
    dumpConfFromStructure,
} from './StructureImporter';
import {IndexLocation} from '../../types/config';
import * as fs from 'fs';

export const phraseanetIndexer: IndexIterator<PhraseanetConfig> =
    async function* (location, logger, databoxClient, options) {
        Twig.extendFilter('escapePath', function (v: string, args: any) {
            return escapePath(v, args[0] ?? '_');
        });

        const phraseanetClient = new PhraseanetClient(location.options, logger);

        const databoxMapping: ConfigDataboxMapping[] = getStrict(
            'databoxMapping',
            location.options
        );
        const idempotencePrefixes: Record<string, string> = {};
        for (const k of [
            'asset',
            'collection',
            'attributeDefinition',
            'renditionDefinition',
        ]) {
            idempotencePrefixes[k] = getConfig(
                `idempotencePrefixes.${k}`,
                phraseanetClient.getId() + '_',
                location.options
            );
        }

        for (const dm of databoxMapping) {
            const phraseanetDatabox = await phraseanetClient.getDatabox(
                dm.databox
            );
            if (phraseanetDatabox === undefined) {
                logger.info(`Unknown databox "${dm.databox}" (ignored)`);
                continue;
            }

            if (dm.renditions === undefined || dm.fieldMap === undefined) {
                dumpConfFromStructure(
                    phraseanetDatabox.databox_id,
                    phraseanetClient,
                    dm,
                    logger
                );
                return;
            }

            logger.info(
                `Start indexing databox "${phraseanetDatabox.name}" (#${phraseanetDatabox.databox_id}) to workspace "${dm.workspaceSlug}"`
            );

            const importFiles: boolean = getConfig(
                'importFiles',
                false,
                location.options
            );

            // scan the conf.fieldMap to get a list of required locales
            const fieldMap: Record<string, any> = dm.fieldMap ?? {};
            let locales: string[] = [];
            for (const k in fieldMap) {
                const fm = fieldMap[k];
                for (const v of fm.values) {
                    if (v.locale !== undefined) {
                        locales.push(v.locale);
                    }
                }
            }
            locales = locales.filter((value, index, a) => {
                return a.indexOf(value) === index;
            });

            let workspaceId =
                await databoxClient.getOrCreateWorkspaceIdWithSlug(
                    dm.workspaceSlug,
                    locales
                );

            if (options.createNewWorkspace) {
                logger.info(`Flushing databox workspace "${dm.workspaceSlug}"`);
                workspaceId = await databoxClient.flushWorkspace(workspaceId);
            }

            const attrPolicyIndex: AttrPolicyIndex = {};
            const defaultPublicPolicy = 'public';
            const name = 'Phraseanet Public';
            logger.info(`Creating "${name}" attribute policy`);
            attrPolicyIndex[defaultPublicPolicy] =
                await databoxClient.createAttributePolicy(defaultPublicPolicy, {
                    name,
                    public: true,
                    editable: true,
                    workspace: `/workspaces/${workspaceId}`,
                    key: defaultPublicPolicy,
                });

            logger.info(`Importing metadata structure`);
            await importMetadataStructure(
                databoxClient,
                workspaceId,
                phraseanetDatabox.databox_id,
                phraseanetClient,
                dm,
                fieldMap,
                idempotencePrefixes,
                attrPolicyIndex[defaultPublicPolicy]['@id'],
                logger
            );

            logger.info(`Importing status-bits structure`);
            const tagIndex = await importStatusBitsStructure(
                databoxClient,
                workspaceId,
                phraseanetDatabox.databox_id,
                phraseanetClient,
                logger
            );

            logger.info(`Importing subdefs structure`);
            const subdefToRendition = await importSubdefsStructure(
                databoxClient,
                workspaceId,
                phraseanetDatabox.databox_id,
                phraseanetClient,
                dm,
                idempotencePrefixes,
                logger
            );

            const collectionKeyPrefix =
                idempotencePrefixes['collection'] +
                phraseanetDatabox.databox_id +
                ':';

            const recordsCollectionPath: string =
                dm.recordsCollectionPath ?? '';
            let recordsCollectionPathTwig: Twig.Template | null = null;
            if (recordsCollectionPath.search(/\{(\{|%)/) !== -1) {
                recordsCollectionPathTwig = Twig.twig({
                    data: recordsCollectionPath,
                });
            }

            const sourceCollections = await getSourceCollections(
                phraseanetDatabox,
                dm,
                logger
            );
            const searchParams = {
                bases: sourceCollections, // if empty (no collections on config) : search all collections
            };

            const importStories = dm.importStories;

            let storiesAsCollections = false;

            const storiesCollectionPath: string =
                dm.storiesCollectionPath ?? '';
            let storiesCollectionPathTwig: Twig.Template | null = null;
            if (dm.storiesCollectionPath !== undefined) {
                storiesAsCollections = true;

                if (storiesCollectionPath.search(/\{(\{|%)/) !== -1) {
                    storiesCollectionPathTwig = Twig.twig({
                        data: storiesCollectionPath,
                    });
                }
            }

            const lockFile = `${process.cwd()}/config/${location.name}_${phraseanetDatabox.name}_${dm.workspaceSlug}.lock`;
            let wip: {last_story: null | string; last_record: null | string} = {
                last_story: null,
                last_record: null,
            };
            try {
                wip = JSON.parse(fs.readFileSync(lockFile, 'utf8'));
            } catch (e) {
                logger.info(`No lock file found, starting from 0`);
            }

            if (importStories !== false) {
                logger.info(`>>> Importing empty stories`);

                let stories: CPhraseanetStory[] = [];

                const ridOperator =
                    phraseanetClient.getSortOrder() === ORDER_ASC ? '>' : '<';
                const PAGESIZE = 20;
                let nStories = 0;
                do {
                    let query = '';
                    if (null !== wip.last_story) {
                        query = `recordid ${ridOperator} ${wip.last_story}`;
                    }
                    logger.info(`search query: ${query}`);

                    stories = await phraseanetClient.searchStories(
                        searchParams,
                        0, // offset,
                        PAGESIZE,
                        query
                    );
                    for (const story of stories) {
                        if (0 === story.children_total) {
                            const {storyCollectionId, storyCollectionFullPath} =
                                await importStory(
                                    story,
                                    location,
                                    dm,
                                    subdefToRendition,
                                    databoxClient,
                                    phraseanetDatabox,
                                    workspaceId,
                                    importFiles,
                                    fieldMap,
                                    tagIndex,
                                    storiesAsCollections,
                                    recordsCollectionPathTwig,
                                    recordsCollectionPath,
                                    storiesCollectionPathTwig,
                                    storiesCollectionPath,
                                    collectionKeyPrefix,
                                    idempotencePrefixes,
                                    logger
                                );
                            logger.info(
                                `  Phraseanet empty story "${story.title}" (#${
                                    story.story_id
                                }) from base "${
                                    phraseanetDatabox.collections[story.base_id]
                                        .name
                                }" (#${story.base_id}) ==> collection "${storyCollectionFullPath}" (#${storyCollectionId})`
                            );
                            nStories++;
                        }

                        wip.last_story = story.story_id;
                        fs.writeFileSync(
                            lockFile,
                            JSON.stringify(wip, null, 2),
                            {flag: 'w'}
                        );
                    }
                } while (stories.length === PAGESIZE);

                logger.info(
                    `<<< End importing ${nStories} empty stories of databox "${phraseanetDatabox.name}" (#${phraseanetDatabox.databox_id})`
                );
            }

            logger.info(`>>> Importing records`);

            let records: CPhraseanetRecord[];
            const ridOperator =
                phraseanetClient.getSortOrder() === ORDER_ASC ? '>' : '<';
            const PAGESIZE = 50;
            let nRecords = 0;
            do {
                let query = dm.searchQuery ?? '';
                if (null !== wip.last_record) {
                    if (query) {
                        query = `(${query}) AND `;
                    }
                    query += `recordid ${ridOperator} ${wip.last_record}`;
                }
                logger.info(`search query: ${query}`);
                records = await phraseanetClient.searchRecords(
                    searchParams,
                    0, // offset
                    PAGESIZE,
                    query,
                    dm.importStories
                );
                for (const record of records) {
                    logger.info(
                        `Phraseanet record "${record.title}" (#${
                            record.record_id
                        }) from base "${
                            phraseanetDatabox.collections[record.base_id].name
                        }" (#${record.base_id})`
                    );

                    const copyTo = [];
                    if (importStories !== false && record.stories.length > 0) {
                        const searchStoriesQuery = record.stories
                            .map(rid => {
                                return `recordid=${rid}`;
                            })
                            .join(' OR ');

                        for (const story of await phraseanetClient.searchStories(
                            [],
                            0, // offset
                            20,
                            searchStoriesQuery
                        )) {
                            const {storyCollectionId, storyCollectionFullPath} =
                                await importStory(
                                    story,
                                    location,
                                    dm,
                                    subdefToRendition,
                                    databoxClient,
                                    phraseanetDatabox,
                                    workspaceId,
                                    importFiles,
                                    fieldMap,
                                    tagIndex,
                                    storiesAsCollections,
                                    recordsCollectionPathTwig,
                                    recordsCollectionPath,
                                    storiesCollectionPathTwig,
                                    storiesCollectionPath,
                                    collectionKeyPrefix,
                                    idempotencePrefixes,
                                    logger
                                );

                            copyTo.push({
                                id: storyCollectionId,
                                path: storyCollectionFullPath,
                            });
                        }
                    }

                    // copy the asset to other location(s) ?
                    for (const ct of dm.copyTo ?? []) {
                        const template = Twig.twig({data: ct});
                        const paths = (
                            await template.renderAsync({
                                record: record,
                                collection:
                                    phraseanetDatabox.collections[
                                        record.base_id
                                    ],
                            })
                        )
                            .split('\n')
                            .map((p: string) => p.trim())
                            .filter((p: string) => p);

                        for (const path of paths) {
                            const branch = splitPath(path);
                            const collId =
                                await databoxClient.createCollectionTreeBranch(
                                    workspaceId,
                                    collectionKeyPrefix,
                                    branch.map(k => ({
                                        key: k,
                                        title: k,
                                    }))
                                );
                            copyTo.push({
                                id: collId,
                                path: path,
                            });
                        }
                    }

                    let path: string = '';
                    if (recordsCollectionPathTwig !== null) {
                        path = await recordsCollectionPathTwig.renderAsync({
                            record: record,
                            collection:
                                phraseanetDatabox.collections[record.base_id],
                        });
                    } else {
                        // bc: dispatch in original phraseanet collection.name
                        path = `${recordsCollectionPath}/${escapeSlashes(phraseanetDatabox.collections[record.base_id].name)}`;
                    }
                    path += '/' + escapeSlashes(record.original_name);

                    yield createAsset(
                        workspaceId,
                        importFiles,
                        record,
                        path,
                        collectionKeyPrefix,
                        idempotencePrefixes['asset'] +
                            record.databox_id +
                            '_' +
                            record.record_id,
                        false,
                        fieldMap,
                        tagIndex,
                        copyTo,
                        dm.sourceFile,
                        subdefToRendition,
                        logger
                    );

                    nRecords++;

                    wip.last_record = record.record_id;
                    fs.writeFileSync(lockFile, JSON.stringify(wip, null, 2), {
                        flag: 'w',
                    });
                }
            } while (records.length == PAGESIZE);
            logger.info(
                `<<< End importing ${nRecords} records of databox "${phraseanetDatabox.name}" (#${phraseanetDatabox.databox_id})`
            );
        }
    };

async function getSourceCollections(
    phraseanetDatabox: PhraseanetDatabox,
    dm: ConfigDataboxMapping,
    logger: Logger
): Promise<string[]> {
    const sourceCollections: string[] = [];
    if (dm.collections) {
        for (const c of dm.collections.split(',')) {
            const collection = phraseanetDatabox.collections[c.trim()];
            if (collection == undefined) {
                logger.info(
                    `Unknown collection "${c.trim()}" into databox "${
                        phraseanetDatabox.name
                    }" (#${phraseanetDatabox.databox_id}) (ignored)`
                );
                continue;
            }
            sourceCollections.push(collection.base_id.toString());
        }
        if (sourceCollections.length === 0) {
            logger.info(
                `No collection found for "${dm.collections}" into databox "${phraseanetDatabox.name}" (#${phraseanetDatabox.databox_id}) (databox ignored)`
            );
        }
    } else {
        for (const baseId of phraseanetDatabox.baseIds) {
            sourceCollections.push(baseId);
        }
    }

    return sourceCollections;
}

async function importStory(
    story: CPhraseanetStory,
    location: IndexLocation<PhraseanetConfig>,
    dm: ConfigDataboxMapping,
    subdefToRendition: Record<string, string[]>,
    databoxClient: DataboxClient,
    phraseanetDatabox: PhraseanetDatabox,
    workspaceId: string,
    importFiles: boolean,
    fieldMap: Record<string, FieldMap>,
    tagIndex: TagIndex,
    storiesAsCollections: boolean,
    recordsCollectionPathTwig: Twig.Template | null,
    recordsCollectionPath: string,
    storiesCollectionPathTwig: Twig.Template | null,
    storiesCollectionPath: string,
    collectionKeyPrefix: string,
    idempotencePrefixes: Record<string, string>,
    logger: Logger
) {
    const storyTitle = escapeSlashes(
        (
            story.title ?? 'story_' + story.databox_id + '_' + story.story_id
        ).trim()
    );
    let storyCollectionBasePath = '';
    let storyCollectionFullPath = '';
    let storyCollectionBasePathParts: string[] = [];
    let storyCollectionId: string;

    if (storiesAsCollections) {
        //
        // create phraseanet stories as visible collections
        //
        storyCollectionBasePathParts = splitPath(
            storiesCollectionPathTwig
                ? await storiesCollectionPathTwig.renderAsync({
                      record: story,
                      collection: phraseanetDatabox.collections[story.base_id],
                  })
                : storiesCollectionPath
        );
        storyCollectionBasePath = storyCollectionBasePathParts.join('/');
        // create the base
        let storyCollectionBaseURI: string | undefined = undefined;
        if (storyCollectionBasePathParts.length > 0) {
            storyCollectionBaseURI =
                '/collections/' +
                (await databoxClient.createCollectionTreeBranch(
                    workspaceId,
                    collectionKeyPrefix,
                    storyCollectionBasePathParts.map(k => ({
                        key: k,
                        title: k,
                    }))
                ));
        }
        // then create the story collection
        storyCollectionId = await databoxClient.createCollection(
            story.resource_id,
            {
                workspaceId: workspaceId,
                key:
                    idempotencePrefixes['collection'] +
                    story.databox_id +
                    '_' +
                    story.story_id,
                title: storiesAsCollections ? storyTitle : undefined,
                parent: storyCollectionBaseURI,
            }
        );
        storyCollectionFullPath =
            (storyCollectionBasePath ?? '') + '/' + storyTitle;
    } else {
        //
        // create phraseanet story as phrasea story (storyAsset + hidden collection)
        //
        let storyAssetBasePath: string = '';
        let storyAssetFullPath: string = '';
        if (recordsCollectionPathTwig !== null) {
            storyAssetBasePath = await recordsCollectionPathTwig.renderAsync({
                record: story,
                collection: phraseanetDatabox.collections[story.base_id],
            });
        } else {
            // bc: dispatch in original phraseanet collection.name
            storyAssetBasePath = `${recordsCollectionPath}/${escapeSlashes(phraseanetDatabox.collections[story.base_id].name)}`;
        }
        storyAssetFullPath = concatPath(
            storyAssetBasePath,
            escapeSlashes(story.title)
        );

        const branch = splitPath(storyAssetBasePath);
        let collId: string;
        try {
            collId = await databoxClient.createCollectionTreeBranch(
                workspaceId,
                collectionKeyPrefix ?? '',
                branch.map(k => ({
                    key: k,
                    title: k,
                }))
            );
        } catch (e: any) {
            logger.error(
                `Failed to create collection branch "${branch.join('/')}": ${e.toString()}`
            );
            throw e;
        }

        const storyAsset = await createAsset(
            workspaceId,
            importFiles,
            story,
            storyAssetFullPath,
            collectionKeyPrefix,
            idempotencePrefixes['asset'] +
                story.databox_id +
                '_' +
                story.story_id,
            true,
            fieldMap,
            tagIndex,
            [],
            dm.sourceFile,
            subdefToRendition,
            logger
        );

        const alternateUrls = getAlternateUrls(storyAsset, location);

        const storyAssetOutput = await databoxClient.createStoryAsset({
            workspaceId: storyAsset.workspaceId,
            sourceFile: storyAsset.publicUrl
                ? {
                      url: storyAsset.publicUrl,
                      isPrivate: storyAsset.isPrivate,
                      alternateUrls,
                      importFile: storyAsset.importFile,
                  }
                : undefined,
            collection: collId ? '/collections/' + collId : undefined,
            generateRenditions: storyAsset.generateRenditions,
            key: storyAsset.key,
            title: storyAsset.title || p.basename(storyAssetBasePath),
            attributes: storyAsset.attributes,
            tags: storyAsset.tags,
            renditions: storyAsset.renditions,
            isStory: storyAsset.isStory,
        });
        console.log(`created story asset ${story.story_id}...`);

        storyCollectionId = storyAssetOutput.storyCollection.id;
        storyCollectionFullPath = '';
    }

    return {storyCollectionId, storyCollectionFullPath};
}
