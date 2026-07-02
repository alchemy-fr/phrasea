import {ApiFile} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import InfoRow from '../Info/InfoRow.tsx';
import KeyIcon from '@mui/icons-material/Key';
import {Divider, MenuList} from '@mui/material';
import {useTranslation} from 'react-i18next';
import InfoIcon from '@mui/icons-material/Info';
import YesNoChip from '../../Ui/YesNoChip.tsx';
import TroubleshootIcon from '@mui/icons-material/Troubleshoot';
import FactCheckIcon from '@mui/icons-material/FactCheck';
import DescriptionIcon from '@mui/icons-material/Description';
import LinkIcon from '@mui/icons-material/Link';
import {formatFilesize} from '../../../lib/filesizeFormatter.ts';

type Props = {
    data: ApiFile;
} & DialogTabProps;

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
                                value={
                                    <pre
                                        style={{
                                            whiteSpace: 'pre-wrap',
                                            wordBreak: 'break-word',
                                        }}
                                    >
                                        {JSON.stringify(data.analysis, null, 2)}
                                    </pre>
                                }
                                icon={<TroubleshootIcon />}
                            />
                        ) : null}
                    </>
                )}
            </MenuList>
        </ContentTab>
    );
}
