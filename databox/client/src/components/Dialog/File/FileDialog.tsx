import {useEffect, useState} from 'react';
import TabbedDialog from '../Tabbed/TabbedDialog';
import {useTranslation} from 'react-i18next';
import {useParams} from '@alchemy/navigation';
import FullPageLoader from '../../Ui/FullPageLoader';
import {File} from '../../../types';
import InfoFile from './InfoFile.tsx';
import {modalRoutes} from '../../../routes';
import FileMetadata from './FileMetadata.tsx';
import {getFile} from '../../../api/file.ts';

type Props = {};

export default function FileDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();

    const [data, setData] = useState<File>();

    useEffect(() => {
        getFile(id!).then(c => setData(c));
    }, [id]);

    if (!data) {
        return <FullPageLoader />;
    }

    return (
        <TabbedDialog
            route={modalRoutes.files.routes.manage}
            routeParams={{id}}
            maxWidth={'md'}
            title={t('file.manage.title', 'Manage File {{name}}', {
                name: data.id,
            })}
            tabs={[
                {
                    title: t('file.manage.info.title', 'Info'),
                    component: InfoFile,
                    id: 'info',
                    props: {
                        data,
                    },
                },
                {
                    title: t('file.manage.metadata.title', 'Metadata'),
                    component: FileMetadata,
                    id: 'metadata',
                    props: {
                        data,
                    },
                },
            ]}
        />
    );
}
