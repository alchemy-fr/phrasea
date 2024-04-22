import SectionDivider from './SectionDivider.tsx';

type Props = {
    page: number;
    top?: number;
};

export default function PageDivider({page, top}: Props) {
    return (
        <SectionDivider
            top={top}
            textSx={() => ({
                fontWeight: 700,
                fontSize: 15,
            })}
        >
            # {page}
        </SectionDivider>
    );
}
