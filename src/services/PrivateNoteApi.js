/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export class PrivateNoteApi {

	async loadNotes(cardId) {
		const api = await axios.get(generateOcsUrl(`apps/deck/api/v1.0/cards/${cardId}/private-notes`), {
			headers: { 'OCS-APIRequest': 'true' },
		})
		return api.data.ocs.data
	}

	async createNote({ cardId, text }) {
		const api = await axios.post(generateOcsUrl(`apps/deck/api/v1.0/cards/${cardId}/private-notes`), {
			text,
		}, {
			headers: { 'OCS-APIRequest': 'true' },
		})
		return api.data.ocs.data
	}

	async updateNote({ cardId, noteId, text }) {
		const api = await axios.put(generateOcsUrl(`apps/deck/api/v1.0/cards/${cardId}/private-notes/${noteId}`), {
			text,
		}, {
			headers: { 'OCS-APIRequest': 'true' },
		})
		return api.data.ocs.data
	}

	async deleteNote({ cardId, noteId }) {
		const api = await axios.delete(generateOcsUrl(`apps/deck/api/v1.0/cards/${cardId}/private-notes/${noteId}`), {
			headers: { 'OCS-APIRequest': 'true' },
		})
		return api.data.ocs.data
	}

}
