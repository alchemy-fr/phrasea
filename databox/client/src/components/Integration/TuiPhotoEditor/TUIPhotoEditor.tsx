import React, {useEffect, useRef, useState} from 'react';
import IntegrationPanelContent from '../Common/IntegrationPanelContent';
import {AssetIntegrationActionsProps} from '../../Media/Asset/FileIntegrations';
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
import {runIntegrationFileAction} from '../../../api/integrations';
import SaveIcon from '@mui/icons-material/Save';
import {dataURLtoFile} from '../../../lib/file';
import {LoadingButton} from '@mui/lab';
import {toast} from 'react-toastify';
import FileOpenIcon from '@mui/icons-material/FileOpen';
import {File} from '../../../types';
import FileItem from './FileItem';

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
    refreshIntegrations,
    enableInc,
}: Props) {
    const editoRef = useRef<any>();
    const [fileName, setFileName] = useState<string>('');
    const [saving, setSaving] = useState<boolean>(false);
    const [selectedFile, setSelectedFile] = useState<File>(file);
    const [deleting, setDeleting] = useState<string | undefined>();

    const saveAs = async () => {
        if (editoRef.current) {
            setSaving(true);
            try {
                await runIntegrationFileAction(
                    'save',
                    integration.id,
                    file.id,
                    {
                        assetId: asset.id,
                        name: fileName,
                    },
                    dataURLtoFile(
                        editoRef.current.getInstance().toDataURL(),
                        file.id
                    )
                );
                toast.success('Saved!');
                await refreshIntegrations();
            } finally {
                setSaving(false);
            }
        }
    };

    const deleteFile = async (id: string) => {
        setDeleting(id);
        try {
            await runIntegrationFileAction('delete', integration.id, file.id, {
                id,
            });
        } finally {
            setDeleting(undefined);
        }
        await refreshIntegrations();
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
                <Typography>Save as</Typography>
                <TextField
                    value={fileName}
                    onChange={e => {
                        setFileName(e.target.value);
                    }}
                    disabled={saving}
                    placeholder={'File name'}
                />
                <LoadingButton
                    sx={{
                        mt: 1,
                    }}
                    startIcon={<SaveIcon />}
                    variant={'contained'}
                    onClick={saveAs}
                    disabled={!fileName}
                    loading={saving}
                >
                    Save
                </LoadingButton>
            </IntegrationPanelContent>

            {integration.data.length > 0 && (
                <List>
                    <ListSubheader>Open recent</ListSubheader>
                    <ListItemButton
                        selected={selectedFile?.id === file.id}
                        onClick={() => onOpen(file!, '')}
                    >
                        <ListItemIcon>
                            <FileOpenIcon />
                        </ListItemIcon>
                        <ListItemText>Original</ListItemText>
                    </ListItemButton>

                    {integration.data.map(d => {
                        return (
                            <FileItem
                                disabled={deleting === d.id}
                                selected={selectedFile?.id === d.value.id}
                                key={d.id}
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
