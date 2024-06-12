import {Asset, Basket, File, WorkspaceIntegration} from '../../types.ts';
import {SetIntegrationOverlayFunction} from '../Media/Asset/AssetView.tsx';

export enum Integration {
    RemoveBg = 'remove.bg',
    AwsRekognition = 'aws.rekognition',
    TuiPhotoEditor = 'tui.photo-editor',
    PhraseaExpose = 'phrasea.expose',
}

type IntegrationActionProps = {
    integration: WorkspaceIntegration;
};

export type BasketIntegrationActionsProps = {
    basket: Basket;
} & IntegrationActionProps;

export type AssetIntegrationActionsProps = {
    asset: Asset;
    file: File;
    setIntegrationOverlay: SetIntegrationOverlayFunction;
    enableInc: number;
} & IntegrationActionProps;
