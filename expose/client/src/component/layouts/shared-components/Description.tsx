type Props = {
    descriptionHtml?: string;
};

export default function Description({
    descriptionHtml
}: Props) {
    if (!descriptionHtml) {
        return ''
    }

    return <div
        className="description"
        dangerouslySetInnerHTML={{
            __html: descriptionHtml,
        }}
    />
}
