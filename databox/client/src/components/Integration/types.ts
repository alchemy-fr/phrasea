import {Asset, Basket, ApiFile, WorkspaceIntegration} from '../../types.ts';
import {SetIntegrationOverlayFunction} from '../Media/Asset/View/AssetView.tsx';
import {AssetAnnotationRef} from '../Media/Asset/Annotations/annotationTypes.ts';

export enum Integration {
    RemoveBg = 'remove.bg',
    AwsRekognition = 'aws.rekognition',
    TuiPhotoEditor = 'tui.photo-editor',
    PhraseaExpose = 'phrasea.expose',
    Matomo = 'matomo',
}

type IntegrationActionProps = {
    integration: WorkspaceIntegration;
};

export type BasketIntegrationActionsProps = {
    basket: Basket;
} & IntegrationActionProps;

export type AssetIntegrationActionsProps = {
    asset: Asset;
    file: ApiFile;
    setIntegrationOverlay: SetIntegrationOverlayFunction;
    assetAnnotationsRef?: AssetAnnotationRef;
    enableInc: number;
    expanded: boolean;
} & IntegrationActionProps;
