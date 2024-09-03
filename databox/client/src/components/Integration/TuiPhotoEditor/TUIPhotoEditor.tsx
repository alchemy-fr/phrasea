import React, {useEffect, useRef, useState} from 'react';
import IntegrationPanelContent from '../Common/IntegrationPanelContent';
import {} from '../../Media/Asset/FileIntegrations';
import {IntegrationOverlayCommonProps} from '../../Media/Asset/AssetView';
import 'tui-image-editor/dist/tui-image-editor.css';
// @ts-expect-error TS error in package
import ImageEditor from '@toast-ui/react-image-editor';
import {
    List,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    ListSubheader,
    TextField,
    Typography,
} from '@mui/material';
import {ObjectType, runIntegrationAction} from '../../../api/integrations';
import SaveIcon from '@mui/icons-material/Save';
import {dataURLtoFile} from '../../../lib/file';
import {LoadingButton} from '@mui/lab';
import {toast} from 'react-toastify';
import FileOpenIcon from '@mui/icons-material/FileOpen';
import {File} from '../../../types';
import FileItem from './FileItem';
import {useChannelRegistration} from '../../../lib/pusher.ts';
import {useIntegrationData} from '../useIntegrationData.ts';
import {AssetIntegrationActionsProps, Integration} from '../types.ts';
import {useTranslation} from 'react-i18next';

const myTheme = {
    // Theme object to extends default dark theme.
};

const PhotoEditor = React.forwardRef<
    any,
    {
        url: string;
        name: string;
    } & IntegrationOverlayCommonProps
>(({url, name, dimensions}, ref) => {
    return (
        <div>
            <ImageEditor
                ref={ref}
                includeUI={{
                    loadImage: {
                        path: url,
                        name,
                    },
                    theme: myTheme,
                    initMenu: 'filter',
                    uiSize: {
                        width: `${dimensions.width}px`,
                        height: `${dimensions.height}px`,
                    },
                    menuBarPosition: 'bottom',
                }}
                cssMaxHeight={dimensions.height}
                cssMaxWidth={dimensions.width}
                selectionStyle={{
                    cornerSize: 20,
                    rotatingPointOffset: 70,
                }}
                usageStatistics={false}
            />
        </div>
    );
});

type Props = {} & AssetIntegrationActionsProps;

export default function TUIPhotoEditor({
    asset,
    file,
    integration,
    setIntegrationOverlay,
    enableInc,
}: Props) {
    const {t} = useTranslation();
    const editoRef = useRef<any>();
    const [fileName, setFileName] = useState<string>('');
    const [saving, setSaving] = useState<boolean>(false);
    const [selectedFile, setSelectedFile] = useState<File>(file);
    const [deleting, setDeleting] = useState<string | undefined>();
    const canEdit = asset.capabilities.canEdit;
    const {data, load: loadData} = useIntegrationData({
        objectType: ObjectType.File,
        objectId: file.id,
        integrationId: integration.id,
        defaultData: integration.data,
    });

    useChannelRegistration(
        `file-${file.id}`,
        `integration:${Integration.TuiPhotoEditor}`,
        () => {
            loadData();
        }
    );

    const saveAs = async () => {
        if (editoRef.current) {
            setSaving(true);
            try {
                await runIntegrationAction(
                    'save',
                    integration.id,
                    {
                        fileId: file.id,
                        assetId: asset.id,
                        name: fileName,
                    },
                    dataURLtoFile(
                        editoRef.current.getInstance().toDataURL(),
                        file.id
                    )
                );
                toast.success(t('tuiphoto_editor.saved', `Saved!`));
                loadData();
            } finally {
                setSaving(false);
            }
        }
    };

    const deleteFile = async (id: string) => {
        setDeleting(id);
        try {
            await runIntegrationAction('delete', integration.id, {
                fileId: file.id,
                id,
            });

            loadData();
        } finally {
            setDeleting(undefined);
        }
    };

    useEffect(() => {
        setSelectedFile(file);
        setFileName('');
    }, [enableInc]);

    useEffect(() => {
        if (enableInc) {
            setIntegrationOverlay(
                PhotoEditor,
                {
                    url: selectedFile.url,
                    name: file.id,
                    ref: editoRef,
                    key: selectedFile.id,
                },
                true
            );
        }
    }, [selectedFile, enableInc]);

    const onOpen = React.useCallback(
        (file: File, name: string | null) => {
            setSelectedFile(file);
            setFileName(name || '');
        },
        [setSelectedFile, setFileName]
    );

    return (
        <>
            <IntegrationPanelContent>
                <Typography>
                    {t('tuiphoto_editor.save_as', `Save as`)}
                </Typography>
                <TextField
                    value={fileName}
                    onChange={e => {
                        setFileName(e.target.value);
                    }}
                    disabled={!canEdit || saving}
                    placeholder={t('tuiphoto_editor.file_name', `File name`)}
                />
                <LoadingButton
                    sx={{
                        mt: 1,
                    }}
                    startIcon={<SaveIcon />}
                    variant={'contained'}
                    onClick={saveAs}
                    disabled={!canEdit || !fileName}
                    loading={saving}
                >
                    {t('tuiphoto_editor.save', `Save`)}
                </LoadingButton>
            </IntegrationPanelContent>

            {data!.pages.length > 0 && (
                <List>
                    <ListSubheader>
                        {t('tuiphoto_editor.open_recent', `Open recent`)}
                    </ListSubheader>
                    <ListItemButton
                        selected={selectedFile?.id === file.id}
                        onClick={() => onOpen(file!, '')}
                    >
                        <ListItemIcon>
                            <FileOpenIcon />
                        </ListItemIcon>
                        <ListItemText>
                            {t('tuiphoto_editor.original', `Original`)}
                        </ListItemText>
                    </ListItemButton>

                    {data!.pages.flat().map(d => {
                        return (
                            <FileItem
                                key={d.id}
                                disabled={deleting === d.id}
                                selected={selectedFile?.id === d.value.id}
                                data={d}
                                onOpen={onOpen}
                                onDelete={deleteFile}
                                asset={asset}
                            />
                        );
                    })}
                </List>
            )}
        </>
    );
}
