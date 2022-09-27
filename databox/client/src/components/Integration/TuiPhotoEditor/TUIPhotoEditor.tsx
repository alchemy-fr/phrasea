import React, {useEffect, useRef, useState} from 'react';
import {File} from "../../../types";
import IntegrationPanelContent from "../Common/IntegrationPanelContent";
import {AssetIntegrationActionsProps} from "../../Media/Asset/FileIntegrations";
import {IntegrationOverlayCommonProps} from "../../Media/Asset/AssetView";
import 'tui-image-editor/dist/tui-image-editor.css';
// @ts-ignore
import ImageEditor from '@toast-ui/react-image-editor';
import {TextField, Typography} from "@mui/material";
import {runIntegrationFileAction} from "../../../api/integrations";
import SaveIcon from '@mui/icons-material/Save';
import {dataURLtoFile} from "../../../lib/file";
import {LoadingButton} from "@mui/lab";
import {toast} from "react-toastify";

const myTheme = {
    // Theme object to extends default dark theme.
};

const PhotoEditor = React.forwardRef<any, {
    file: File
} & IntegrationOverlayCommonProps>(({
                                        file,
                                        maxDimensions,
                                    }, ref) => {
    return <div>
        <ImageEditor
            ref={ref}
            includeUI={{
                loadImage: {
                    path: file.url,
                    name: file.id,
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
                                           enableInc,
                                       }: Props) {
    const editoRef = useRef<any>();
    const [fileName, setFileName] = useState<string>('');
    const [saving, setSaving] = useState<boolean>(false);

    const saveAs = async () => {
        if (editoRef.current) {
            setSaving(true);
            try {
                await runIntegrationFileAction('save', integration.id, file.id, {
                    assetId: asset.id,
                    name: fileName,
                }, dataURLtoFile(editoRef.current.getInstance().toDataURL(), file.id));
                toast.success('Saved!');
            } catch (e) {
            }
            setSaving(false);
        }
    };

    useEffect(() => {
        setIntegrationOverlay(PhotoEditor, {
            file,
            ref: editoRef,
        }, true);
    }, [enableInc, file]);

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
                startIcon={<SaveIcon/>}
                variant={'contained'}
                onClick={saveAs}
                disabled={!fileName}
                loading={saving}
            >
                Save
            </LoadingButton>
        </IntegrationPanelContent>
    </>
}
