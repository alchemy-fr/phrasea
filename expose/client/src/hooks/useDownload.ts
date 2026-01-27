import {useModals} from '@alchemy/navigation';
import {Publication} from '../types.ts';
import DownloadTermsDialog from '../components/publication/layouts/common/DownloadTermsDialog.tsx';
import {isTermsAccepted, setAcceptedTerms} from '../lib/terms.ts';
import DownloadViaEmailDialog from '../components/publication/layouts/common/DownloadViaEmailDialog.tsx';

type Props = {
    publication: Publication;
    newWindow?: boolean;
};

export function useDownload({publication, newWindow}: Props) {
    const {openModal} = useModals();

    return (url: string) => {
        if (!publication.downloadEnabled) {
            return;
        }

        const dl = () => {
            if (publication.downloadViaEmail) {
                openModal(DownloadViaEmailDialog, {
                    url,
                });

                return;
            }

            const link = document.createElement('a');
            link.href = url;
            link.download = '';
            if (newWindow) {
                link.target = '_blank';
            }
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };

        const termsKey = `pd_${publication.id}`;

        if (publication.downloadTerms?.enabled && !isTermsAccepted(termsKey)) {
            openModal(DownloadTermsDialog, {
                terms: publication.downloadTerms,
                onAccept: () => {
                    setAcceptedTerms(termsKey);
                    dl();
                },
            });

            return;
        }

        dl();
    };
}
