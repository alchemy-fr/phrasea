import {NodeViewContent, NodeViewWrapper} from '@tiptap/react';
import {useTranslation} from 'react-i18next';

type Props = {};

export default function CarouselWidget({}: Props) {
    const {t} = useTranslation();
    return (
        <NodeViewWrapper className="react-component">
            <label contentEditable={false}>
                {t('landing.editor.carousel_widget', 'Carousel widget')}
            </label>

            <NodeViewContent className="content is-editable" />
        </NodeViewWrapper>
    );
}
