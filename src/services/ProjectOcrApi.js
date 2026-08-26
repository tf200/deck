/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const headers = {
	'OCS-APIRequest': 'true',
	'Content-Type': 'application/json',
}

export class ProjectOcrApi {

	async getProjectByBoard(boardId) {
		try {
			const response = await axios.get(generateUrl(`/apps/projectcreatoraio/api/v1/projects/board/${boardId}`), { headers })
			return response?.data ?? null
		} catch (error) {
			if (error?.response?.status === 404) {
				return null
			}
			throw error
		}
	}

	async listProjectDocumentTypes(projectId) {
		const response = await axios.get(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/ocr/document-types`), { headers })
		return response?.data?.document_types ?? []
	}

	async getFileProcessing(projectId, fileId) {
		try {
			const response = await axios.get(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/files/${fileId}/ocr`), { headers })
			return response?.data ?? null
		} catch (error) {
			if (error?.response?.status === 404) {
				return null
			}
			throw error
		}
	}

	async uploadCardAttachment(projectId, cardId, documentTypeId, file, onUploadProgress) {
		const data = new FormData()
		data.append('document_type_id', String(documentTypeId))
		data.append('storage_scope', 'shared')
		data.append('file', file)
		const response = await axios.post(
			generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/cards/${cardId}/ocr/attachments`),
			data,
			{ headers: { 'OCS-APIRequest': 'true' }, onUploadProgress },
		)
		return response?.data ?? null
	}

	async finalizeCardAttachment(projectId, cardId, processingId, fields) {
		const response = await axios.post(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/cards/${cardId}/ocr/attachments/finalize`), {
			processing_id: processingId,
			fields,
		}, { headers })
		return response?.data ?? null
	}

	async reprocessFileProcessing(projectId, fileId) {
		const response = await axios.post(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/files/${fileId}/ocr/reprocess`), {}, { headers })
		return response?.data ?? null
	}

	async updateFileExtractedFields(projectId, fileId, fields) {
		const response = await axios.put(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/files/${fileId}/ocr/extracted`), { fields }, { headers })
		return response?.data ?? null
	}

}
