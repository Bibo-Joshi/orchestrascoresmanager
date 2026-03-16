// Typed client wrapper that instantiates generated API classes and binds them to the Nextcloud axios instance.

import ncAxios from '@nextcloud/axios'
import type { AxiosInstance } from '@nextcloud/axios'

// Import generated API classes
import { Ocs as DefaultOCS } from './generated/openapi/Ocs'
import { Ocs as AdminOCS } from './generated/openapi-administration/Ocs'

export type ApiClients = {
  default: DefaultOCS,
  admin: AdminOCS;
};

export function createClient(axiosInstance: AxiosInstance = ncAxios): ApiClients {
	const ocs = new DefaultOCS()
	const adminOcs = new AdminOCS()
	// Generated HttpClient exposes `instance: AxiosInstance` publicly — override it to use Nextcloud's axios
	// eslint-disable-next-line @typescript-eslint/ban-ts-comment
	// @ts-ignore assign generated instance
	for (const client of [ocs, adminOcs]) client.instance = axiosInstance

	return {
		default: ocs,
		admin: adminOcs,
	}
}

export const apiClients = createClient(ncAxios)
