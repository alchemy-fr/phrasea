import {DataboxClient} from '../../databox/client';
import PhraseanetClient from './phraseanetClient';
import {
    ConfigDataboxMapping,
    ConfigPhraseanetSubdef,
    FieldMap,
    PhraseanetSubdefStruct,
} from './types';
import {Logger} from 'winston';
import {
    attributeTypesEquivalence,
    DataboxAttributeType,
    TagIndex,
} from './shared';
import {AttributeDefinition, Tag} from '../../databox/types';
import Twig from 'twig';
import Yaml from 'js-yaml';

enum RenditionBuildMode {
    COPY_ASSET_FILE = 1,
    BUILD_FROM_PARENT = 2,
}

export async function dumpConfFromStructure(
    phraseanetDataboxId: string,
    phraseanetClient: PhraseanetClient,
    original_dm: ConfigDataboxMapping,
    logger: Logger
) {
    const dm: ConfigDataboxMapping = JSON.parse(JSON.stringify(original_dm)); // = copy
    // patch to enforce creation
    dm.renditions = undefined;
    dm.sourceFile = undefined;
    dm.fieldMap = undefined;
    await addMissingRenditionsConf(phraseanetDataboxId, phraseanetClient, dm);
    await addMissingAttributeDefinitionsConf(
        phraseanetDataboxId,
        phraseanetClient,
        dm
    );

    logger.info(
        "**** Missing 'renditions' or 'fieldMap' configuration ****\n" +
            '____ Phraseanet full configuration equivalent:\n' +
            JSON.stringify(dm, null, 2) +
            '\n' +
            '____ Copy/paste/adapt the completed configuration above ____'
    );
}

export async function addMissingRenditionsConf(
    phraseanetDataboxId: string,
    phraseanetClient: PhraseanetClient,
    dm: ConfigDataboxMapping
) {
    const subdefs =
        await phraseanetClient.getSubdefsStruct(phraseanetDataboxId);

    dm.sourceFile = 'document';

    // import all subdefs from phraseanet
    dm['renditions'] = {
        original: {
            useAsOriginal: true,
            buildMode: RenditionBuildMode.COPY_ASSET_FILE,
            policy: 'original',
        } as ConfigPhraseanetSubdef,
    };

    for (const sd of subdefs) {
        if (!dm.renditions[sd.name]) {
            dm.renditions[sd.name] = {
                policy: sd.class,
                buildMode: RenditionBuildMode.BUILD_FROM_PARENT,
                parent: 'original',
                useAsPreview: sd.name === 'preview' ? true : undefined,
                useAsThumbnail: sd.name === 'thumbnail' ? true : undefined,
                useAsThumbnailActive:
                    sd.name === 'thumbnailgif' ? true : undefined,
                builders: {},
            };
        }
        dm.renditions[sd.name].builders[sd.type] = {
            from: `${sd.type}:${sd.name}`,
        };
    }
}

export async function addMissingAttributeDefinitionsConf(
    phraseanetDataboxId: string,
    phraseanetClient: PhraseanetClient,
    dm: ConfigDataboxMapping
) {
    const metaStructure =
        await phraseanetClient.getMetaStruct(phraseanetDataboxId);

    if (!dm.fieldMap) {
        dm.fieldMap = {}; //Record<string, FieldMap>

        // import all fields from structure
        for (const name in metaStructure) {
            dm.fieldMap[name] = {
                id: 'meta_' + metaStructure[name].id,
                type:
                    attributeTypesEquivalence[metaStructure[name].type] ??
                    DataboxAttributeType.Text,
                multivalue: metaStructure[name].multivalue,
                readonly: metaStructure[name].readonly,
                translatable: false,
                labels: metaStructure[name].labels,
                values: [
                    {
                        type: 'metadata',
                        value: name,
                    },
                ],
                attributeDefinition: {} as AttributeDefinition,
            };
        }
    }
    dm.fieldMap['phr_record_id'] = {
        id: 'phr_record_id',
        position: 0,
        type: DataboxAttributeType.Number,
        multivalue: false,
        readonly: true,
        translatable: false,
        labels: {},
        values: [
            {
                type: 'template',
                value: '{{record.record_id}}',
            },
        ],
        attributeDefinition: {} as AttributeDefinition,
    };

    dm.fieldMap['phr_created_on'] = {
        id: 'phr_created_on',
        position: 0,
        type: DataboxAttributeType.DateTime,
        multivalue: false,
        readonly: true,
        translatable: false,
        labels: {},
        values: [
            {
                type: 'template',
                value: '{{record.created_on}}',
            },
        ],
        attributeDefinition: {} as AttributeDefinition,
    };
}

export async function importSubdefsStructure(
    databoxClient: DataboxClient,
    workspaceId: string,
    phraseanetDataboxId: string,
    phraseanetClient: PhraseanetClient,
    dm: ConfigDataboxMapping,
    idempotencePrefixes: Record<string, string>,
    logger: Logger
): Promise<Record<string, string[]>> {
    const policyIndex: Record<string, string> = {};
    const renditionPolicies =
        await databoxClient.getRenditionPolicies(workspaceId);
    renditionPolicies.forEach(rc => {
        policyIndex[rc.name] = rc.id;
    });

    const sdByName: Record<
        string,
        {
            name: string;
            parent: string | null;
            buildMode: number;
            useAsOriginal: boolean;
            useAsPreview: boolean;
            useAsThumbnail: boolean;
            useAsThumbnailActive: boolean;
            types: Record<string, PhraseanetSubdefStruct>;
            policy: string | null;
            labels: Record<string, string>;
        }
    > = {};

    const subdefToRendition = {} as Record<string, string[]>;

    const subdefs =
        await phraseanetClient.getSubdefsStruct(phraseanetDataboxId);

    if (dm.renditions === false) {
        // special value: do not create rendition definitions
        return {};
    }

    for (const name in dm.renditions) {
        const rendition = dm.renditions[name];
        if (!sdByName[name]) {
            sdByName[name] = {
                name: name,
                parent: rendition.parent ?? null,
                useAsOriginal: rendition.useAsOriginal ?? false,
                buildMode:
                    rendition.buildMode ??
                    (rendition.builders
                        ? RenditionBuildMode.BUILD_FROM_PARENT
                        : RenditionBuildMode.COPY_ASSET_FILE),
                useAsPreview: rendition.useAsPreview ?? false,
                useAsThumbnail: rendition.useAsThumbnail ?? false,
                useAsThumbnailActive: rendition.useAsThumbnailActive ?? false,
                types: {} as Record<string, PhraseanetSubdefStruct>,
                policy: rendition['policy'] ?? null,
                labels: {},
            };
        }

        for (const [family, settings] of Object.entries(
            rendition.builders ?? []
        )) {
            if ('build' in settings && 'from' in settings) {
                logger.error(
                    `  Rendition-definition "${name}" for family "${family}": Use "build" OR "from", not both. Rendition definition ignored`
                );
                continue;
            }
            if ('build' in settings) {
                // hardcoded
            }
            if ('from' in settings) {
                // find the subdef with good name and family
                const [sdFamily, sdName] = settings['from'].split(':');
                const sd = subdefs.find(
                    sd => sd.name === sdName && sd.type === sdFamily
                );
                if (!sd) {
                    logger.error(`  Subdef "${settings['from']}" not found`);
                    continue;
                }
                if (sdByName[name].types[sd.type]) {
                    logger.error(
                        `  Build "${sd.type}" for rendition "${name}" already set`
                    );
                    continue;
                }
                if (!subdefToRendition[settings['from']]) {
                    subdefToRendition[settings['from']] = [];
                }
                subdefToRendition[settings['from']].push(name);
                sdByName[name].types[sd.type] = sd;
                sdByName[name].labels = sd.labels; // todo: check conflicts
                if (!rendition.policy) {
                    // use phrnet class
                    if (sdByName[name].policy === null) {
                        sdByName[name].policy = sd.class;
                    }
                    // sd of same name should have the same class
                    if (
                        sdByName[name].policy !== sd.class &&
                        sdByName[name].policy !== 'mixed'
                    ) {
                        logger.info(
                            `  Rendition "${name}" gets different policies ("${sdByName[sd.name].policy}" and "${sd.class}": "mixed" is used)`
                        );
                        sdByName[name].policy = 'mixed';
                    }
                }
            }
        }
    }

    const renditionIdByName = {} as Record<string, string>;

    for (const sdName in sdByName) {
        const sd = sdByName[sdName];

        if (!sd.policy) {
            logger.info(
                `  Rendition definition "${sdName}" has neither "class" or phraseanet "from": using policy "public"`
            );
            sd.policy = 'public';
        }

        if (!policyIndex[sd.policy]) {
            logger.info(`  Creating rendition policy "${sd.policy}" `);
            policyIndex[sd.policy] = await databoxClient.createRenditionPolicy({
                name: sd.policy,
                workspace: `/workspaces/${workspaceId}`,
                public: true,
            });
        }

        logger.info(
            `  Creating rendition definition "${sd.name}" of class "${sd.policy}"`
        );
        const jsConf: Record<string, object> = {};
        const translators: Record<string, typeof translateImageSettings> = {
            image: translateImageSettings,
            video: translateVideoSettings,
            audio: translateAudioSettings,
            document: translateDocumentSettings,
        };
        for (const family in sd.types) {
            if (translators[family]) {
                jsConf[family] = translators[family](sd.types[family]);
            }
        }

        if (sd['parent'] && !renditionIdByName[sd['parent']]) {
            logger.error(
                `    Parent rendition definition "${sd['parent']}" for "${sd.name}" not found: no parent set. Check declaration order`
            );
            sd['parent'] = null;
        }

        renditionIdByName[sd.name] =
            await databoxClient.createRenditionDefinition({
                name: sd.name,
                parent: sd['parent']
                    ? `/rendition-definitions/${renditionIdByName[sd['parent']]}`
                    : null,
                key: `${idempotencePrefixes['renditionDefinition']}${sd.name}`,
                policy: `/rendition-policies/${policyIndex[sd.policy]}`,
                buildMode: sd.buildMode,
                useAsOriginal: sd.useAsOriginal,
                useAsPreview: sd.useAsPreview,
                useAsThumbnail: sd.useAsThumbnail,
                useAsThumbnailActive: sd.name === 'thumbnailgif',
                priority: 0,
                workspace: `/workspaces/${workspaceId}`,
                labels: {
                    phraseanetDefinition: sd.labels,
                },
                definition: Yaml.dump(jsConf, {lineWidth: 100}).trim(),
            });
    }

    return subdefToRendition;
}

export async function importMetadataStructure(
    databoxClient: DataboxClient,
    workspaceId: string,
    phraseanetDataboxId: string,
    phraseanetClient: PhraseanetClient,
    dm: ConfigDataboxMapping,
    fieldMap: Record<string, FieldMap>,
    idempotencePrefixes: Record<string, string>,
    attrPolicy: string,
    logger: Logger
): Promise<Record<string, FieldMap>> {
    const metaStructure =
        await phraseanetClient.getMetaStruct(phraseanetDataboxId);

    const attributeDefinitionIndex: Record<string, AttributeDefinition> = {};
    let ufid = 0; // used to generate a unique id for fields declared in conf, but not existing in phraseanet
    let position = 1;
    for (const name in fieldMap) {
        const fm = fieldMap[name];
        fm.id = metaStructure[name]
            ? metaStructure[name].id
            : (--ufid).toString();
        fm.position = position++;
        fm.multivalue =
            fm.multivalue ??
            (metaStructure[name] ? metaStructure[name].multivalue : false);
        fm.readonly =
            fm.readonly ??
            (metaStructure[name] ? metaStructure[name].readonly : false);
        fm.labels =
            fm.labels ??
            (metaStructure[name] ? metaStructure[name].labels : {});
        fm.type =
            fm.type ??
            (metaStructure[name]
                ? attributeTypesEquivalence[metaStructure[name].type]
                : DataboxAttributeType.Text);
        for (const v of fm.values) {
            if (v.locale !== undefined) {
                fm.translatable = true;
            }

            if (v.type === 'template') {
                try {
                    v.twig = Twig.twig({data: v.value}); // compile once
                } catch (e: any) {
                    throw new Error(
                        `Error compiling twig for field "${name}": ${e.message}`
                    );
                }
            }
        }

        if (!attributeDefinitionIndex[name]) {
            const data = {
                key: `${idempotencePrefixes['attributeDefinition']}_${name}_${fm.type}_${fm.multivalue ? '1' : '0'}`,
                name: name,
                position: fm.position,
                editable: !fm.readonly,
                multiple: fm.multivalue,
                fieldType: attributeTypesEquivalence[fm.type ?? ''] || fm.type,
                workspace: `/workspaces/${workspaceId}`,
                policy: attrPolicy,
                labels: fm.labels,
                translatable: fm.translatable,
            };
            logger.info(`  Creating "${name}" attribute definition`);
            attributeDefinitionIndex[name] =
                await databoxClient.createAttributeDefinition(fm.id, data);
        }
        fm.attributeDefinition = attributeDefinitionIndex[name];
    }

    dm.fieldMap = fieldMap;
    return fieldMap;
}

export async function importStatusBitsStructure(
    databoxClient: DataboxClient,
    workspaceId: string,
    phraseanetDataboxId: string,
    phraseanetClient: PhraseanetClient,
    logger: Logger
): Promise<TagIndex> {
    const tagIndex: TagIndex = {};
    for (const sb of await phraseanetClient.getStatusBitsStruct(
        phraseanetDataboxId
    )) {
        logger.info(`  Creating "${sb.label_on}" tag`);
        const key =
            phraseanetClient.getId() +
            '_' +
            phraseanetDataboxId +
            '.sb' +
            sb.bit;
        const tag: Tag = await databoxClient.createTag(key, {
            workspace: `/workspaces/${workspaceId}`,
            name: sb.label_on,
        });
        tagIndex[sb.bit] = '/tags/' + tag.id;
    }

    return tagIndex;
}

function translateDocumentSettings(sd: PhraseanetSubdefStruct): object {
    // too bad: phraseanet api does not provide the target "mediatype" (image, video, ...)
    // so we guess from the presence of option "icodec"
    if (sd.options['icodec']) {
        return translateDocumentSettings_withIcodec(sd);
    }
    // here no icodec: pdf or flexpaper (flexpaper is not handled by phrasea, so import as pdf)
    return translateDocumentSettings_toPdf();
}

function translateDocumentSettings_withIcodec(
    sd: PhraseanetSubdefStruct
): object {
    return {
        transformations: [
            {
                module: 'document_to_pdf',
            },
            {
                module: 'pdf_to_image',
                options: {
                    size: [sd.options['size'], sd.options['size']],
                    resolution: sd.options['resolution'],
                    extension: sd.options['icodec'],
                },
            },
        ],
    };
}

function translateDocumentSettings_toPdf(): object {
    return {
        transformations: [
            {
                module: 'document_to_pdf',
            },
        ],
    };
}

function translateImageSettings(sd: PhraseanetSubdefStruct): object {
    // todo: extension ?
    const size = sd.options['size'];
    const icodecFormats: Record<string, string> = {
        jpeg: 'jpeg',
        png: 'png',
        tiff: 'tiff',
    };
    const format: string = icodecFormats[sd.options.icodec] ?? '';
    if (!format) {
        throw new Error(
            `Unsupported image codec: ${sd.options.icodec} for subdef image:${sd.name}`
        );
    }

    const filters: Record<string, any> = {
        auto_rotate: null,
    };

    const bgcolor = sd.options.backgroundcolor?.['0'] ?? '';
    if (bgcolor) {
        filters.background_fill = {
            color: bgcolor,
            opacity: 100,
        };
    }
    filters.thumbnail = {
        size: [size, size],
        mode: 'inset',
    };

    return {
        transformations: [
            {
                module: 'imagine',
                options: {
                    format,
                    filters,
                },
            },
            {
                module: 'set_dpi',
                options: {
                    dpi: sd.options['resolution'],
                },
            },
        ],
    };
}

function translateVideoSettings(sd: PhraseanetSubdefStruct): object {
    // too bad: phraseanet api does not provide the target "mediatype" (image, video, ...)
    // so we guess from the presence of option(s) "icodec", "vcodec", "acodec"
    if (sd.options['vcodec']) {
        // also have a acodec, so test first
        return translateVideoSettings_withVcodec(sd);
    }
    if (sd.options['acodec']) {
        // here no vcodec: pure audio
        return translateVideoSettings_withAcodec(sd);
    }
    if (sd.options['icodec']) {
        return translateVideoSettings_withIcodec(sd);
    }
    return {};
}

function translateVideoSettings_withVcodec(sd: PhraseanetSubdefStruct): object {
    // todo : gop
    const formatMap: Record<string, string> = {
        libvpx: 'video-webm',
        libtheora: 'video-webm',
        libx264: 'video-mpeg4',
    };
    const format = formatMap[sd.options['vcodec']] ?? '';
    if (!format) {
        throw new Error(
            `Unsupported video codec: ${sd.options['vcodec']} for subdef video: ${sd.name}`
        );
    }

    const size = sd.options['size'] ?? 100;

    const ffmpegModuleOptions: any = {
        format,
        timeout: 7200,
        filters: [
            {
                name: 'resize',
                width: size,
                height: size,
                mode: 'inset',
            },
        ],
    };
    // in phraseanet, "audiobitrate" is already in K !
    const audiokbrate = sd.options['audiobitrate'] ?? 0;
    if (audiokbrate > 0) {
        ffmpegModuleOptions['audio_kilobitrate'] = audiokbrate;
    }

    if (null !== sd.options['acodec']) {
        const audioCodecs = [
            'libfaac',
            'libvo_aacenc',
            'libmp3lame',
            'libvorbis',
            'libfdk_aac',
        ];
        if (!audioCodecs.includes(sd.options['acodec'])) {
            throw new Error(
                `Unsupported audio codec: ${sd.options['acodec']} for subdef video: ${sd.name}`
            );
        }
        ffmpegModuleOptions['audio_codec'] = sd.options['acodec'];
    }

    const audioSamplerate = sd.options['audiosamplerate'] ?? 0;
    if (audioSamplerate > 0) {
        ffmpegModuleOptions['filters'].push({
            name: 'resample_audio',
            rate: audioSamplerate,
        });
    }

    return {
        transformations: [
            {
                module: 'ffmpeg',
                options: ffmpegModuleOptions,
            },
        ],
    };
}

function translateVideoSettings_withAcodec(sd: PhraseanetSubdefStruct): object {
    return translateAudioSettings_withAcodec(sd);
}

function translateVideoSettings_withIcodec(sd: PhraseanetSubdefStruct): object {
    if (sd.options['delay'] === undefined) {
        // a static image
        return translateVideoSettings_targetImageFrame(sd);
    } else {
        // a animated gif (ignore icodec, always use gif)
        return translateVideoSettings_targetAnimatedGif(sd);
    }
}

function translateVideoSettings_targetImageFrame(
    sd: PhraseanetSubdefStruct
): object {
    const formatMap: Record<string, string> = {
        jpeg: 'image-jpeg',
        png: 'image-png',
        tiff: 'image-tiff',
    };
    const format = formatMap[sd.options['icodec']] ?? '';
    if (!format) {
        throw new Error(
            `Unsupported image codec: ${sd.options['icodec']} for subdef video: ${sd.name}`
        );
    }

    const transformations: Array<object> = (translateImageSettings(sd) as any)
        .transformations;

    transformations.unshift({
        module: 'video_to_frame',
        options: {
            format,
            start: 0,
        },
    });

    return {
        transformations,
    };
}

function translateVideoSettings_targetAnimatedGif(
    sd: PhraseanetSubdefStruct
): object {
    const size = sd.options['size'] ?? 100;
    // fps from (msec)delay, with 2 decimals
    const fps = Math.round(100000.0 / sd.options['delay']) / 100;

    return {
        transformations: [
            {
                module: 'video_to_animation',
                options: {
                    format: 'animated-gif',
                    start: 0,
                    duration: 5,
                    fps: fps,
                    width: size,
                    height: size,
                },
            },
        ],
    };
}

function translateAudioSettings(sd: PhraseanetSubdefStruct): object {
    // too bad: phraseanet api does not provide the target "mediatype" (image, video, ...)
    // so we guess from the presence of option(s) "icodec", "acodec"
    if (sd.options['acodec']) {
        // here no vcodec: pure audio
        return translateAudioSettings_withAcodec(sd);
    }
    if (sd.options['icodec']) {
        return translateAudioSettings_withIcodec(sd);
    }
    return {};
}

function translateAudioSettings_withAcodec(sd: PhraseanetSubdefStruct): object {
    const formatMap: Record<string, string> = {
        pcm_s16le: 'audio-wav',
        libmp3lame: 'audio-mp3',
        flac: 'audio-aac',
    };
    const format = formatMap[sd.options['acodec']] ?? '';
    if (!format) {
        throw new Error(
            `Unsupported audio codec: ${sd.options['acodec']} for subdef video: ${sd.name}`
        );
    }

    const ffmpegModuleOptions: Record<string, any> = {
        format,
        timeout: 7200,
    };

    // in phraseanet, "audiobitrate" is already in K !
    const audiokbrate = sd.options['audiobitrate'] ?? 0;
    if (audiokbrate > 0) {
        ffmpegModuleOptions['audio_kilobitrate'] = audiokbrate;
    }

    const audioSamplerate = sd.options['audiosamplerate'] ?? 0;
    if (audioSamplerate > 0) {
        ffmpegModuleOptions['filters'] = [
            {
                name: 'resample_audio',
                rate: audioSamplerate,
            },
        ];
    }

    return {
        transformations: [
            {
                module: 'ffmpeg',
                options: ffmpegModuleOptions,
            },
        ],
    };
}

function translateAudioSettings_withIcodec(sd: PhraseanetSubdefStruct): object {
    const icodecs = ['jpeg', 'png', 'tiff'];
    if (!icodecs.includes(sd.options['icodec'])) {
        throw new Error(
            `Unsupported image codec: ${sd.options['icodec']} for subdef video: ${sd.name}`
        );
    }

    const size = sd.options['size'] ?? 100;

    return {
        transformations: [
            {
                module: 'album_artwork',
                options: {
                    format: 'image-' + sd.options['icodec'],
                },
            },
            {
                module: 'imagine',
                options: {
                    filters: {
                        thumbnail: {
                            size: [size, size],
                        },
                    },
                },
            },
        ],
    };
}
