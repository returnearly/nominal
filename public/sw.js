/* Nominal web push service worker */
self.addEventListener('push', (event) => {
    let data = { title: 'Nominal', body: 'Monitor alert', url: '/admin' }

    try {
        if (event.data) {
            data = { ...data, ...event.data.json() }
        }
    } catch (error) {
        console.error('Nominal push payload parse failed', error)
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Nominal', {
            body: data.body || '',
            data: { url: data.url || '/admin' },
            icon: '/favicon.svg',
            badge: '/favicon.svg',
        }),
    )
})

self.addEventListener('notificationclick', (event) => {
    event.notification.close()

    const url = event.notification.data?.url || '/admin'

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url.includes('/admin') && 'focus' in client) {
                    return client.focus()
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(url)
            }
        }),
    )
})
