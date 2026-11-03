import {notifyNavigationStart} from '@/lib/navigation/navigationProgress';

export function onRouterTransitionStart(url: string): void {
    notifyNavigationStart(url);
}
