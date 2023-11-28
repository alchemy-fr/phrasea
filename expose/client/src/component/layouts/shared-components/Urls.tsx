type Url = {
    text: string;
    url: string;
}

type Props = {
    urls: Url[];
};

export default function Urls({
    urls
}: Props) {

    if (urls.length === 0) {
        return ''
    }

    return <ul className="urls">
        {urls.map((url) => (
            <li key={url.url}>
                <a href={url.url}>{url.text}</a>
            </li>
        ))}
    </ul>
}

