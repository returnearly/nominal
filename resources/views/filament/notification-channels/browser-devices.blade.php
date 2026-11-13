@php
    /** @var \App\Models\NotificationChannel $channel */
    $channel = $this->getRecord();
    $publicKey = $channel->configArray()['vapid_public_key'] ?? '';
    $subscriptions = $channel->pushSubscriptions()->with('user')->latest()->get();
@endphp

<div
    wire:key="browser-devices-{{ $channel->id }}-{{ $subscriptions->count() }}"
    class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    x-data="nominalBrowserPush(@js(['publicKey' => $publicKey]))"
>
    <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
        <div class="flex flex-col gap-1">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                Devices
            </h3>
            <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                Enable this browser to receive OS notifications when monitors alert on this channel.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button
                color="primary"
                size="sm"
                x-show="!enabled"
                x-on:click="enable()"
                x-bind:disabled="busy"
            >
                Enable on this device
            </x-filament::button>

            <x-filament::button
                color="gray"
                size="sm"
                x-show="enabled"
                x-cloak
                x-on:click="disable()"
                x-bind:disabled="busy"
            >
                Disable on this device
            </x-filament::button>

            <p class="text-sm text-danger-600 dark:text-danger-400" x-show="error" x-text="error" x-cloak></p>
            <p class="text-sm text-gray-500 dark:text-gray-400" x-show="supported === false" x-cloak>
                This browser does not support web push notifications.
            </p>
        </div>
    </div>

    <div class="fi-section-content-ctn border-t border-gray-200 dark:border-white/10">
        <div class="fi-section-content overflow-x-auto px-6 py-4">
            @if ($subscriptions->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No devices subscribed yet.</p>
            @else
                <table class="w-full table-auto text-start text-sm">
                    <thead>
                        <tr class="text-gray-500 dark:text-gray-400">
                            <th class="pb-2 pe-4 font-medium">User</th>
                            <th class="pb-2 pe-4 font-medium">Browser</th>
                            <th class="pb-2 pe-4 font-medium">Added</th>
                            <th class="pb-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($subscriptions as $subscription)
                            <tr wire:key="push-{{ $subscription->id }}">
                                <td class="py-2 pe-4 text-gray-950 dark:text-white">
                                    {{ $subscription->user?->email ?? 'Local operator' }}
                                </td>
                                <td class="max-w-xs truncate py-2 pe-4 text-gray-500 dark:text-gray-400" title="{{ $subscription->user_agent }}">
                                    {{ $subscription->user_agent ?: '—' }}
                                </td>
                                <td class="py-2 pe-4 text-gray-500 dark:text-gray-400">
                                    {{ $subscription->created_at?->diffForHumans() }}
                                </td>
                                <td class="py-2 text-end">
                                    <x-filament::button
                                        color="danger"
                                        size="xs"
                                        wire:click="removeBrowserDevice('{{ $subscription->id }}')"
                                        wire:confirm="Remove this device from the channel?"
                                    >
                                        Remove
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>

@script
<script>
    Alpine.data('nominalBrowserPush', (config) => ({
        publicKey: config.publicKey,
        supported: null,
        enabled: false,
        busy: false,
        error: null,
        currentEndpoint: null,

        async init() {
            this.supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window

            if (! this.supported) {
                return
            }

            try {
                const registration = await navigator.serviceWorker.register('/sw.js')
                await navigator.serviceWorker.ready
                const subscription = await registration.pushManager.getSubscription()
                this.enabled = Boolean(subscription)
                this.currentEndpoint = subscription?.endpoint ?? null
            } catch (error) {
                this.error = error?.message || 'Unable to check push support.'
            }
        },

        urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
            const raw = atob(base64)
            const output = new Uint8Array(raw.length)

            for (let i = 0; i < raw.length; i++) {
                output[i] = raw.charCodeAt(i)
            }

            return output
        },

        async enable() {
            this.busy = true
            this.error = null

            try {
                const permission = await Notification.requestPermission()

                if (permission !== 'granted') {
                    throw new Error('Notification permission was not granted.')
                }

                const registration = await navigator.serviceWorker.register('/sw.js')
                await navigator.serviceWorker.ready

                let subscription = await registration.pushManager.getSubscription()

                if (! subscription) {
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: this.urlBase64ToUint8Array(this.publicKey),
                    })
                }

                const json = subscription.toJSON()

                await this.$wire.subscribeBrowserDevice({
                    endpoint: json.endpoint,
                    public_key: json.keys.p256dh,
                    auth_token: json.keys.auth,
                    content_encoding: 'aes128gcm',
                    user_agent: navigator.userAgent,
                })

                this.enabled = true
                this.currentEndpoint = json.endpoint
            } catch (error) {
                this.error = error?.message || 'Unable to enable browser notifications.'
            } finally {
                this.busy = false
            }
        },

        async disable() {
            this.busy = true
            this.error = null

            try {
                const registration = await navigator.serviceWorker.ready
                const subscription = await registration.pushManager.getSubscription()

                if (subscription) {
                    const endpoint = subscription.endpoint
                    await subscription.unsubscribe()
                    await this.$wire.unsubscribeBrowserDevice(endpoint)
                }

                this.enabled = false
                this.currentEndpoint = null
            } catch (error) {
                this.error = error?.message || 'Unable to disable browser notifications.'
            } finally {
                this.busy = false
            }
        },
    }))
</script>
@endscript
