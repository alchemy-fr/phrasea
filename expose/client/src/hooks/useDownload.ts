import {useModals} from '@alchemy/navigation';
import {Publication} from '../types.ts';
import DownloadTermsDialog from '../components/publication/layouts/common/DownloadTermsDialog.tsx';
import {isTermsAccepted, setAcceptedTerms} from '../lib/terms.ts';

type Props = {
    url: string;
    publication: Publication;
    newWindow?: boolean;
};

export function useDownload({url, publication, newWindow}: Props) {
    const {openModal} = useModals();

    return () => {
        if (!publication.downloadEnabled) {
            return;
        }

        const dl = () => {
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

        if (publication.downloadTerms && !isTermsAccepted(termsKey)) {
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
