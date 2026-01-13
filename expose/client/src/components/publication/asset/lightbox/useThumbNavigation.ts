import {useNavigateToPublication} from '../../../../hooks/useNavigateToPublication.ts';
import {Asset, Publication, Thumb} from '../../../../types.ts';
import {useEffect, useMemo} from 'react';

type Props = {
    publication: Publication;
    thumbs: Thumb[];
    asset?: Asset;
};

export function useThumbNavigation({publication, thumbs, asset}: Props) {
    const navigateToPublication = useNavigateToPublication();

    const {close, goNext, goPrevious} = useMemo(() => {
        const handler = (inc: number) => () => {
            if (asset) {
                const currentIndex = thumbs.findIndex(t => t.id === asset.id);
                const newIndex =
                    (currentIndex + inc + thumbs.length) % thumbs.length;

                navigateToPublication(publication, thumbs[newIndex].id);
            }
        };

        return {
            goNext: handler(1),
            goPrevious: handler(-1),
            close: () => {
                navigateToPublication(publication);
            },
        };
    }, [thumbs, navigateToPublication, publication, asset]);

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                close();
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                goNext();
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                goPrevious();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        const originalOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = originalOverflow;
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [goNext, goPrevious, close]);

    return {close, goNext, goPrevious};
}
