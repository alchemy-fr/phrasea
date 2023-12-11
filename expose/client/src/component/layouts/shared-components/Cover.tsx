type Props = {
    url: string;
    alt?: string;
};

export default function Cover({url, alt}: Props) {
    return (
        <div className="cover">
            <img src={url} alt={alt || 'Cover'} />
        </div>
    );
}
