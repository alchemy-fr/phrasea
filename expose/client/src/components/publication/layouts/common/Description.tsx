type Props = {
    descriptionHtml?: string;
};

export default function Description({descriptionHtml}: Props) {
    if (!descriptionHtml) {
        return '';
    }

    return (
        <div
            dangerouslySetInnerHTML={{
                __html: descriptionHtml,
            }}
        />
    );
}
