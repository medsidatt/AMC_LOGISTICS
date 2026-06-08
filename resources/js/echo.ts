import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'pusher'>;
    }
}

// Singleton Echo instance for the whole SPA. Picks up Vite-exposed env so
// the same code works in dev and production. Pusher is the broadcast layer
// (free tier handles ~80 trucks at our broadcast cadence comfortably).
window.Pusher = Pusher;

const key = import.meta.env.VITE_PUSHER_APP_KEY as string | undefined;
const cluster = (import.meta.env.VITE_PUSHER_APP_CLUSTER as string | undefined) ?? 'eu';

let echo: Echo<'pusher'> | null = null;

export function getEcho(): Echo<'pusher'> | null {
    if (echo) return echo;
    if (!key) {
        // Build / SSR without Pusher creds — degrade gracefully.
        if (typeof window !== 'undefined' && import.meta.env.DEV) {
            // eslint-disable-next-line no-console
            console.warn('[echo] VITE_PUSHER_APP_KEY is not set; live updates disabled.');
        }
        return null;
    }
    echo = new Echo({
        broadcaster: 'pusher',
        key,
        cluster,
        forceTLS: true,
    });
    window.Echo = echo;
    return echo;
}
