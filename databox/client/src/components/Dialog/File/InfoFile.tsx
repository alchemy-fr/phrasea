import {ApiFile} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import InfoRow from '../Info/InfoRow.tsx';
import KeyIcon from '@mui/icons-material/Key';
import {Divider, MenuList} from '@mui/material';
import {useTranslation} from 'react-i18next';
import InfoIcon from '@mui/icons-material/Info';

type Props = {
    data: ApiFile;
} & DialogTabProps;

export default function InfoFile({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
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
                    value={data.url}
                    copyValue={data.url}
                    icon={<InfoIcon />}
                />
                <InfoRow
                    label={t('file.info.type', `Type`)}
                    value={data.type}
                    copyValue={data.type}
                    icon={<InfoIcon />}
                />
                <InfoRow
                    label={t('file.info.size', `Size`)}
                    value={data.size}
                    copyValue={data.size?.toString()}
                    icon={<InfoIcon />}
                />
                {data.analysisPending ? (
                    <InfoRow
                        label={t(
                            'file.info.analysis_pending',
                            `Analysis Pending`
                        )}
                        value={t('common.yes', 'Yes')}
                        icon={<InfoIcon />}
                    />
                ) : (
                    <>
                        <InfoRow
                            label={t('file.info.accepted', `Accepted`)}
                            value={
                                data.accepted
                                    ? t('common.yes', 'Yes')
                                    : t('common.no', 'No')
                            }
                            icon={<InfoIcon />}
                        />
                        {data.analysis ? (
                            <InfoRow
                                label={t('file.info.analysis', `Analysis`)}
                                value={
                                    <pre>
                                        {JSON.stringify(data.analysis, null, 2)}
                                    </pre>
                                }
                                icon={<InfoIcon />}
                            />
                        ) : null}
                    </>
                )}
            </MenuList>
        </ContentTab>
    );
}
