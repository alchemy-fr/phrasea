import {ApiFile, FileUsage} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import InfoRow from '../Info/InfoRow.tsx';
import KeyIcon from '@mui/icons-material/Key';
import {Divider, Link, MenuList, Stack} from '@mui/material';
import {useTranslation} from 'react-i18next';
import InfoIcon from '@mui/icons-material/Info';
import YesNoChip from '../../Ui/YesNoChip.tsx';
import TroubleshootIcon from '@mui/icons-material/Troubleshoot';
import FactCheckIcon from '@mui/icons-material/FactCheck';
import DescriptionIcon from '@mui/icons-material/Description';
import LinkIcon from '@mui/icons-material/Link';
import AccountTreeIcon from '@mui/icons-material/AccountTree';
import {formatFilesize} from '../../../lib/filesizeFormatter.ts';
import FileAnalysisReport from './FileAnalysisReport.tsx';
import {modalRoutes} from '../../../routes.ts';
import {useNavigateToModal} from '../../Routing/ModalLink.tsx';

type Props = {
    data: ApiFile;
} & DialogTabProps;

function FileUsages({usages}: {usages: FileUsage[]}) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();

    const resolveLabel = (usage: FileUsage): string => {
        const assetTitle = usage.assetTitle || usage.assetId;

        switch (usage.type) {
            case 'source':
                return t('file.usages.source', {
                    defaultValue: 'Source of asset "{{asset}}"',
                    asset: assetTitle,
                });
            case 'version':
                return t('file.usages.version', {
                    defaultValue: 'Version "{{name}}" of asset "{{asset}}"',
                    name: usage.name,
                    asset: assetTitle,
                });
            case 'rendition':
                return t('file.usages.rendition', {
                    defaultValue: 'Rendition "{{name}}" of asset "{{asset}}"',
                    name: usage.name,
                    asset: assetTitle,
                });
        }
    };

    return (
        <Stack spacing={0.5}>
            {usages.map((usage, index) => (
                <Link
                    key={index}
                    component={'button'}
                    type={'button'}
                    sx={{textAlign: 'left'}}
                    onClick={() => {
                        navigateToModal(modalRoutes.assets.routes.manage, {
                            tab: 'info',
                            id: usage.assetId,
                        });
                    }}
                >
                    {resolveLabel(usage)}
                </Link>
            ))}
        </Stack>
    );
}

export default function InfoFile({data, onClose, minHeight}: Props) {
    const {t, i18n} = useTranslation();
    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <MenuList>
                <InfoRow
                    label={t('file.info.id', `ID`)}
                    value={data.id}
                    copyValue={data.id}
                    icon={<KeyIcon />}
                />
                <Divider />
                <InfoRow
                    label={t('file.info.url', `URL`)}
                    value={data.url || t('file.info.url_none', `N/A`)}
                    copyValue={data.url}
                    icon={<LinkIcon />}
                />
                <InfoRow
                    label={t('file.info.type', `Type`)}
                    value={data.type}
                    copyValue={data.type}
                    icon={<DescriptionIcon />}
                />
                <InfoRow
                    label={t('file.info.usages', `Referenced by`)}
                    value={
                        data.usages && data.usages.length > 0 ? (
                            <FileUsages usages={data.usages} />
                        ) : (
                            t('file.info.usages_none', `Not referenced`)
                        )
                    }
                    icon={<AccountTreeIcon />}
                />
                <InfoRow
                    label={t('file.info.size', `Size`)}
                    value={
                        data.size
                            ? formatFilesize(t, data.size, true, i18n.language)
                            : t('file.info.size_unknown', `Unknown`)
                    }
                    copyValue={data.size ? data.size?.toString() : undefined}
                    icon={<InfoIcon />}
                />
                {data.analysisPending ? (
                    <InfoRow
                        label={t(
                            'file.info.analysis_pending',
                            `Analysis Pending`
                        )}
                        value={t('common.yes', 'Yes')}
                        icon={<FactCheckIcon />}
                    />
                ) : (
                    <>
                        <InfoRow
                            label={t('file.info.accepted', `Accepted`)}
                            value={
                                undefined !== data.accepted ? (
                                    <YesNoChip value={data.accepted} />
                                ) : null
                            }
                            icon={<FactCheckIcon />}
                        />
                        {data.analysis ? (
                            <InfoRow
                                label={t('file.info.analysis', `Analysis`)}
                                value={<FileAnalysisReport file={data} />}
                                icon={<TroubleshootIcon />}
                            />
                        ) : null}
                    </>
                )}
            </MenuList>
        </ContentTab>
    );
}
