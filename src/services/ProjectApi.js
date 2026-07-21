/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export class ProjectApi {

	async getBoardMappings() {

		try {
			const response = await axios.get(generateUrl('/apps/projectcreatoraio/api/v1/projects/board-mappings'), {
				headers: {
					'OCS-APIRequest': 'true',
				},
			})
			return response.data
		} catch {
			// ProjectCreator AIO is optional, so ordinary Deck installations have no mappings.
			return []
		}

	}

}
