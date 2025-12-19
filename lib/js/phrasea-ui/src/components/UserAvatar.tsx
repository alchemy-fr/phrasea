import Avatar, {AvatarProps} from '@mui/material/Avatar';

type Props = {
    size: number;
    username: string | undefined | null;
} & AvatarProps;

const cachedColors: Record<string, string> = {};

function generateHSL(name: string): string {
    const getHashOfString = (str: string) => {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        hash = Math.abs(hash);
        return hash;
    };

    function normalizeHash(hash: number, min: number, max: number) {
        return Math.floor((hash % (max - min)) + min);
    }

    const hash = getHashOfString(name);
    const h = normalizeHash(hash, 0, 360);
    const s = normalizeHash(hash, 50, 75);
    const l = normalizeHash(hash, 25, 60);

    return `hsl(${h}, ${s}%, ${l}%)`;
}

function getUsernameColor(username: string | null | undefined): string {
    if (!username) {
        username = 'U';
    }
    if (cachedColors[username]) {
        return cachedColors[username];
    }
    cachedColors[username] = generateHSL(username);

    return cachedColors[username];
}

export default function UserAvatar({size, username, ...props}: Props) {
    return (
        <Avatar
            style={{
                width: size,
                height: size,
                fontSize: size / 1.5,
                backgroundColor: getUsernameColor(username),
                color: '#FFF',
            }}
            alt={username || undefined}
            {...props}
        >
            {(username ? username[0] : 'U').toUpperCase()}
        </Avatar>
    );
}
