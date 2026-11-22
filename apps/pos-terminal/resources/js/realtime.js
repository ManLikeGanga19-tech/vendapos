import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/*
 * Listens on this till's private channel for "sync now" and hands it to the
 * status bar (StatusBar::syncNow), which pulls the change through the API.
 * The subscription is signed by the till's own PHP (/realtime/auth), so the
 * device token never reaches this window. If the connection drops, the
 * 60-second background sync carries on; on reconnect we sync at once.
 */
export function startRealtime() {
    // The status bar carries where to listen. A till paired a moment ago, or
    // a server with it switched off, has none yet: look again later.
    const holder = document.querySelector('[data-vendapos-realtime]');

    if (!holder) {
        setTimeout(startRealtime, 5000);

        return;
    }

    const config = JSON.parse(holder.dataset.vendaposRealtime);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const syncNow = (what) => window.Livewire?.dispatch('till-sync', { what });

    window.Pusher = Pusher;

    const echo = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                fetch('/realtime/auth', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
                })
                    .then((response) => (response.ok ? response.json() : Promise.reject(new Error(`auth ${response.status}`))))
                    .then((data) => callback(null, data))
                    .catch((error) => callback(error, null));
            },
        }),
    });

    echo.private(config.channel).listen(`.${config.event}`, (event) => syncNow(event.what));

    // Anything missed while disconnected: one sync as soon as the connection is back.
    let wasConnected = false;
    echo.connector.pusher.connection.bind('state_change', ({ current }) => {
        if (current === 'connected') {
            if (wasConnected) {
                syncNow('reconnect');
            }
            wasConnected = true;
        }
    });

    window.vendaposRealtime = echo;
}
