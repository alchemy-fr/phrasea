import React, {useEffect, useRef, useState} from 'react';
import IntegrationPanelContent from "../Common/IntegrationPanelContent";
import {AssetIntegrationActionsProps} from "../../Media/Asset/FileIntegrations";
import {IntegrationOverlayCommonProps} from "../../Media/Asset/AssetView";
import 'tui-image-editor/dist/tui-image-editor.css';
import {MouseEvent as ReactMouseEvent} from 'react';
// @ts-ignore
import ImageEditor from '@toast-ui/react-image-editor';
import {
    IconButton,
    List,
    ListItemButton,
    ListItemIcon,
    ListItemSecondaryAction,
    ListItemText,
    ListSubheader,
    TextField,
    Typography
} from "@mui/material";
import {runIntegrationFileAction} from "../../../api/integrations";
import SaveIcon from '@mui/icons-material/Save';
import {dataURLtoFile} from "../../../lib/file";
import {LoadingButton} from "@mui/lab";
import {toast} from "react-toastify";
import FileOpenIcon from '@mui/icons-material/FileOpen';
import DeleteIcon from '@mui/icons-material/Delete';

const myTheme = {
    // Theme object to extends default dark theme.
};

const PhotoEditor = React.forwardRef<any, {
    url: string;
    name: string;
} & IntegrationOverlayCommonProps>(({
                                        url,
                                        name,
                                        maxDimensions,
                                    }, ref) => {
    return <div>
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
                    width: `${maxDimensions.width}px`,
                    height: `${maxDimensions.height}px`,
                },
                menuBarPosition: 'bottom',
            }}
            cssMaxHeight={maxDimensions.height}
            cssMaxWidth={maxDimensions.width}
            selectionStyle={{
                cornerSize: 20,
                rotatingPointOffset: 70,
            }}
            usageStatistics={false}
        />
    </div>
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
    const [selectedFile, setSelectedFile] = useState(file.url);
    const [deleting, setDeleting] = useState<string | undefined>();

    const saveAs = async () => {
        if (editoRef.current) {
            setSaving(true);
            try {
                await runIntegrationFileAction('save', integration.id, file.id, {
                    assetId: asset.id,
                    name: fileName,
                }, dataURLtoFile(editoRef.current.getInstance().toDataURL(), file.id));
                toast.success('Saved!');
                await refreshIntegrations();
            } catch (e) {
            }
            setSaving(false);
        }
    };

    const deleteFile = async (e: ReactMouseEvent<HTMLButtonElement, MouseEvent>, id: string) => {
        e.stopPropagation();
        setDeleting(id);
        await runIntegrationFileAction('delete', integration.id, file.id, {
            id,
        });
        await refreshIntegrations();
        setDeleting(undefined);
    }

    useEffect(() => {
        setSelectedFile(file.url);
        setFileName('');
    }, [enableInc]);

    useEffect(() => {
        setIntegrationOverlay(PhotoEditor, {
            url: selectedFile,
            name: file.id,
            ref: editoRef,
            key: selectedFile,
        }, true);
    }, [selectedFile]);

    const onOpen = (url: string, name: string | null) => {
        setSelectedFile(url);
        setFileName(name || '');
    }

    return <>
        <IntegrationPanelContent>
            <Typography>
                Save as
            </Typography>
            <TextField
                value={fileName}
                onChange={(e) => {
                    setFileName(e.target.value);
                }}
                disabled={saving}
                placeholder={'File name'}
            />
            <LoadingButton
                sx={{
                    mt: 1,
                }}
                startIcon={<SaveIcon/>}
                variant={'contained'}
                onClick={saveAs}
                disabled={!fileName}
                loading={saving}
            >
                Save
            </LoadingButton>
        </IntegrationPanelContent>

        <List>
            <ListSubheader>
                Open recent
            </ListSubheader>
            {integration.data.map(d => {
                return <ListItemButton
                    disabled={deleting === d.id}
                    selected={selectedFile === d.value}
                    key={d.id}
                    onClick={() => onOpen(d.value, d.keyId)}
                >
                    <ListItemIcon>
                        <FileOpenIcon/>
                    </ListItemIcon>
                    <ListItemText>
                        {d.keyId}
                    </ListItemText>

                    <ListItemSecondaryAction>
                        <IconButton
                            onMouseDown={e => e.stopPropagation()}
                            onMouseUp={e => e.stopPropagation()}
                            onClick={(e) => deleteFile(e, d.id)}
                            disabled={deleting === d.id}
                            color={'error'}
                        >
                            <DeleteIcon/>
                        </IconButton>
                    </ListItemSecondaryAction>
                </ListItemButton>
            })}
        </List>
    </>
}
