declare module '@nextcloud/event-bus' {
	interface NextcloudEvents {
		'orchestrascoresmanager:sidebar:content-set': { key: string; content: string }
		'orchestrascoresmanager:sidebar:toggle': void
	}
	export function emit(event: keyof NextcloudEvents, payload: NextcloudEvents[keyof NextcloudEvents]): void
	export function subscribe(event: keyof NextcloudEvents, callback: (payload: NextcloudEvents[keyof NextcloudEvents]) => void): void
	export function unsubscribe(event: keyof NextcloudEvents, callback?: (payload: NextcloudEvents[keyof NextcloudEvents]) => void): void
}
