type Props = {
    text: string;
};

export default function Copyright({text}: Props) {
    if (!text) {
        return '';
    }

    return <div className="copy-text">{text}</div>;
}
