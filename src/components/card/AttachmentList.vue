<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<AttachmentDragAndDrop :card-id="cardId"
		:defer-upload="ocrAvailable"
		class="drop-upload--sidebar"
		@files-dropped="handleDroppedFiles">
		<div v-if="!isReadOnly" class="button-group">
			<NcButton v-if="canUploadLocalFiles" class="icon-upload" @click="uploadNewFile()">
				{{ t('deck', 'Upload new files') }}
			</NcButton>
			<NcButton class="icon-folder" @click="shareFromFiles()">
				{{ t('deck', 'Share from Files') }}
			</NcButton>
		</div>
		<input ref="filesAttachment"
			type="file"
			style="display: none;"
			multiple
			@change="handleUploadFile">
		<ul class="attachment-list">
			<li v-for="attachment in uploadQueue" :key="attachment.name" class="attachment">
				<a class="fileicon" :style="mimetypeForAttachment()" />
				<div class="details">
					<a>
						<div class="filename">
							<span>{{ attachmentBasename(attachment) }}</span>
							<span class="extension">.{{ attachmentExtension(attachment) }}</span>
						</div>
						<progress :value="attachment.progress" max="100" />
					</a>
				</div>
			</li>
			<li v-for="attachment in attachments"
				:key="attachment.id"
				class="attachment"
				:class="{ 'attachment--deleted': attachment.deletedAt > 0 }">
				<a class="fileicon"
					:href="internalLink(attachment)"
					:style="mimetypeForAttachment(attachment)"
					@click.prevent="showViewer(attachment)" />
				<div class="details">
					<a :href="internalLink(attachment)" @click.prevent="showViewer(attachment)">
						<div class="filename">
							<span>{{ attachmentBasename(attachment) }}</span>
							<span class="extension">.{{ attachmentExtension(attachment) }}</span>
						</div>
						<div v-if="attachment.deletedAt === 0">
							<span class="filesize">{{ formattedFileSize(attachment.extendedData.filesize) }}</span>
							<span class="filedate">{{ relativeDate(attachment.createdAt*1000) }}</span>
							<span class="filedate">{{ attachment.extendedData.attachmentCreator.displayName }}</span>
						</div>
						<div v-else>
							<span class="attachment--info">{{ t('deck', 'Pending share') }}</span>
						</div>
					</a>
					<div v-if="showOcrForAttachment(attachment)" class="attachment__ocr" @click.stop>
						<span class="attachment__ocr-type">{{ processingDocumentTypeLabel(attachmentFileId(attachment)) }}</span>
						<span class="attachment__ocr-status" :class="`attachment__ocr-status--${ocrStatus(attachmentFileId(attachment))}`">
							{{ ocrStatusLabel(attachmentFileId(attachment)) }}
						</span>
						<NcButton v-if="canOpenExtractedData(attachmentFileId(attachment))"
							type="tertiary-no-background"
							@click="openExtractedData(attachmentFileId(attachment))">
							{{ t('deck', 'Extracted data') }}
						</NcButton>
						<NcButton v-if="canReprocess(attachmentFileId(attachment))"
							type="tertiary-no-background"
							:disabled="ocrBusyByFileId[String(attachmentFileId(attachment))]"
							@click="reprocessAttachment(attachment)">
							{{ t('deck', 'Reprocess') }}
						</NcButton>
					</div>
				</div>
				<NcActions v-if="selectable">
					<NcActionButton icon="icon-confirm" @click="$emit('select-attachment', attachment)">
						{{ t('deck', 'Add this attachment') }}
					</NcActionButton>
				</NcActions>
				<NcActions v-if="removable && !isReadOnly" :force-menu="true">
					<NcActionLink v-if="attachment.extendedData.fileid" icon="icon-folder" :href="internalLink(attachment)">
						{{ t('deck', 'Show in Files') }}
					</NcActionLink>
					<NcActionLink v-if="attachment.extendedData.fileid"
						icon="icon-download"
						:href="downloadLink(attachment)"
						download>
						{{ t('deck', 'Download') }}
					</NcActionLink>
					<NcActionButton v-if="attachment.extendedData.fileid && !isReadOnly" icon="icon-delete" @click="unshareAttachment(attachment)">
						{{ t('deck', 'Remove attachment') }}
					</NcActionButton>

					<NcActionButton v-if="!attachment.extendedData.fileid && attachment.deletedAt === 0" icon="icon-delete" @click="$emit('delete-attachment', attachment)">
						{{ t('deck', 'Delete Attachment') }}
					</NcActionButton>
					<NcActionButton v-else-if="!attachment.extendedData.fileid" icon="icon-history" @click="$emit('restore-attachment', attachment)">
						{{ t('deck', 'Restore Attachment') }}
					</NcActionButton>
				</NcActions>
			</li>
		</ul>

		<NcModal v-if="showOcrUploadModal" :title="t('deck', 'Upload and process files')" @close="closeOcrUploadModal">
			<div class="attachment__ocr-modal">
				<label for="attachment-ocr-document-type">{{ t('deck', 'Document type') }}</label>
				<select id="attachment-ocr-document-type" v-model="uploadDocumentTypeId" :disabled="ocrUploadBusy">
					<option value="">
						{{ t('deck', 'Select document type...') }}
					</option>
					<option v-for="documentType in documentTypes" :key="documentType.id" :value="String(documentType.id)">
						{{ documentType.name }}
					</option>
				</select>
				<NcButton :disabled="ocrUploadBusy" @click="$refs.filesAttachment.click()">
					{{ t('deck', 'Choose files') }}
				</NcButton>
				<span>{{ selectedUploadFilesLabel }}</span>
				<div class="attachment__ocr-actions">
					<NcButton :disabled="ocrUploadBusy" @click="closeOcrUploadModal">
						{{ t('deck', 'Cancel') }}
					</NcButton>
					<NcButton type="primary"
						:disabled="ocrUploadBusy || !uploadDocumentTypeId || selectedUploadFiles.length === 0"
						@click="uploadSelectedFiles">
						{{ ocrUploadBusy ? t('deck', 'Uploading...') : t('deck', 'Upload and process') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<NcModal v-if="activeExtractedFileId" :title="t('deck', 'Extracted data')" @close="closeExtractedData">
			<div class="attachment__ocr-modal">
				<p v-if="activeExtractedEntries.length === 0">
					{{ t('deck', 'No data extracted yet.') }}
				</p>
				<label v-for="entry in activeExtractedEntries" :key="entry.key" class="attachment__ocr-field">
					<span>{{ entry.name }}</span>
					<input :value="activeExtractedDraft[entry.key] ?? ''"
						:class="{ 'attachment__ocr-input--missing': entry.missing }"
						@input="$set(activeExtractedDraft, entry.key, $event.target.value)">
				</label>
				<div class="attachment__ocr-actions">
					<NcButton type="primary" :disabled="savingExtracted || isReadOnly" @click="saveExtractedData">
						{{ savingExtracted ? t('deck', 'Saving...') : t('deck', 'Save extracted fields') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</AttachmentDragAndDrop>
</template>

<script>
import axios from '@nextcloud/axios'
import { NcActions, NcActionButton, NcActionLink, NcButton, NcModal } from '@nextcloud/vue'
import AttachmentDragAndDrop from '../AttachmentDragAndDrop.vue'
import relativeDate from '../../mixins/relativeDate.js'
import { formatFileSize } from '@nextcloud/files'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl, generateOcsUrl, generateRemoteUrl } from '@nextcloud/router'
import { mapState, mapActions } from 'vuex'
import { loadState } from '@nextcloud/initial-state'
import attachmentUpload from '../../mixins/attachmentUpload.js'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { ProjectOcrApi } from '../../services/ProjectOcrApi.js'
const maxUploadSizeState = loadState('deck', 'maxUploadSize', -1)
const projectOcrApi = new ProjectOcrApi()
const SUPPORTED_OCR_MIME_TYPES = [
	'application/pdf',
	'image/jpeg',
	'image/png',
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	'application/vnd.ms-excel',
]

const picker = getFilePickerBuilder(t('deck', 'File to share'))
	.setMultiSelect(false)
	.setType(1)
	.allowDirectories()
	.build()

export default {
	name: 'AttachmentList',
	components: {
		NcActions,
		NcActionButton,
		NcActionLink,
		NcButton,
		NcModal,
		AttachmentDragAndDrop,
	},
	mixins: [relativeDate, attachmentUpload],

	props: {
		cardId: {
			type: Number,
			required: true,
		},
		selectable: {
			type: Boolean,
			required: false,
		},
		removable: {
			type: Boolean,
			required: false,
		},
	},
	data() {
		return {
			modalShow: false,
			file: '',
			overwriteAttachment: null,
			isDraggingOver: false,
			maxUploadSize: maxUploadSizeState,
			projectId: null,
			documentTypes: [],
			processingByFileId: {},
			ocrBusyByFileId: {},
			pendingAttachmentByFileId: {},
			showOcrUploadModal: false,
			uploadDocumentTypeId: '',
			selectedUploadFiles: [],
			ocrUploadBusy: false,
			activeExtractedFileId: null,
			activeExtractedDraft: {},
			savingExtracted: false,
		}
	},
	computed: {
		canUploadLocalFiles() {
			const storageStats = loadState('files', 'storageStats', { quota: -1 })
			return storageStats.quota !== 0
		},
		attachments() {
			// FIXME sort propertly by last modified / deleted at
			return [...this.$store.getters.attachmentsByCard(this.cardId)].filter(attachment => attachment.deletedAt >= 0).sort((a, b) => b.id - a.id)
		},
		mimetypeForAttachment() {
			return (attachment) => {
				if (!attachment) {
					return {}
				}
				const url = attachment.extendedData.hasPreview ? this.attachmentPreview(attachment) : OC.MimeType.getIconUrl(attachment.extendedData.mimetype)
				const styles = {
					'background-image': `url("${url}")`,
				}
				return styles
			}
		},
		attachmentPreview() {
			return (attachment) => (attachment.extendedData.fileid ? generateUrl(`/core/preview?fileId=${attachment.extendedData.fileid}&x=64&y=64`) : null)
		},
		attachmentUrl() {
			return (attachment) => generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
		},
		internalLink() {
			return (attachment) => generateUrl('/f/' + attachment.extendedData.fileid)
		},
		downloadLink() {
			return (attachment) => generateRemoteUrl(`dav/files/${getCurrentUser().uid}/${attachment.extendedData.path}`)
		},
		formattedFileSize() {
			return (filesize) => formatFileSize(filesize)
		},
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		isReadOnly() {
			return !this.$store.getters.canEdit
		},
		boardId() {
			const boardId = Number(this.currentBoard?.id ?? this.$route?.params?.id)
			return Number.isFinite(boardId) && boardId > 0 ? boardId : null
		},
		ocrAvailable() {
			return this.removable && !this.selectable && Number(this.projectId) > 0 && this.documentTypes.length > 0
		},
		selectedUploadFilesLabel() {
			return this.selectedUploadFiles.length === 0
				? t('deck', 'No files selected yet.')
				: t('deck', '{count} file(s) selected.', { count: this.selectedUploadFiles.length })
		},
		activeExtractedEntries() {
			return this.extractedEntries(this.activeExtractedFileId)
		},
		dropHintText() {
			if (this.isReadOnly) {
				return t('deck', 'This board is read only')
			} else {
				return t('deck', 'Drop your files to upload')
			}
		},
		attachmentBasename() {
			return (attachment) => attachment?.extendedData?.info.filename
				?? (attachment?.name ?? attachment.data).replace(/\.[^/.]+$/, '')
		},
		attachmentExtension() {
			return (attachment) => attachment?.extendedData?.info?.extension
				?? (attachment?.name ?? attachment.data).split('.').pop()
		},
		cardDetailsInModal() {
			return this.$store.getters.config('cardDetailsInModal')
		},
	},
	watch: {
		cardId: {
			immediate: true,
			async handler() {
				this.processingByFileId = {}
				this.ocrBusyByFileId = {}
				this.pendingAttachmentByFileId = {}
				this.activeExtractedFileId = null
				this.activeExtractedDraft = {}
				await this.fetchAttachments(this.cardId)
				this.loadAttachmentProcessing()
			},
		},
		boardId: {
			immediate: true,
			handler() {
				this.initializeOcr()
			},
		},
		attachments() {
			this.loadAttachmentProcessing()
		},
	},
	methods: {
		...mapActions([
			'fetchAttachments',
		]),
		handleUploadFile(event) {
			const files = Array.from(event.target.files ?? [])
			event.target.value = ''
			if (this.showOcrUploadModal) {
				this.selectedUploadFiles = files
				return
			}
			for (const file of files) {
				this.onLocalAttachmentSelected(file, 'file')
			}
		},
		uploadNewFile() {
			if (this.ocrAvailable) {
				this.showOcrUploadModal = true
				return
			}
			this.$refs.filesAttachment.click()
		},
		handleDroppedFiles(files) {
			if (!this.ocrAvailable) {
				for (const file of files) {
					this.onLocalAttachmentSelected(file, 'file')
				}
				return
			}
			this.selectedUploadFiles = files
			this.showOcrUploadModal = true
		},
		closeOcrUploadModal() {
			if (this.ocrUploadBusy) return
			this.showOcrUploadModal = false
			this.uploadDocumentTypeId = ''
			this.selectedUploadFiles = []
		},
		async initializeOcr() {
			const boardId = this.boardId
			this.projectId = null
			this.documentTypes = []
			this.processingByFileId = {}
			if (!this.removable || this.selectable || !boardId) return
			try {
				const project = await projectOcrApi.getProjectByBoard(boardId)
				if (boardId !== this.boardId) return
				if (!Number(project?.id)) return
				this.projectId = Number(project.id)
				const documentTypes = await projectOcrApi.listProjectDocumentTypes(this.projectId)
				if (boardId !== this.boardId) return
				this.documentTypes = documentTypes
				this.loadAttachmentProcessing()
			} catch (error) {
				console.error('Could not initialize attachment OCR', error)
			}
		},
		attachmentFileId(attachment) {
			return Number(attachment?.extendedData?.fileid ?? 0)
		},
		showOcrForAttachment(attachment) {
			return this.ocrAvailable
				&& Number(attachment?.deletedAt) === 0
				&& this.attachmentFileId(attachment) > 0
				&& SUPPORTED_OCR_MIME_TYPES.includes(String(attachment?.extendedData?.mimetype || '').toLowerCase())
		},
		async loadAttachmentProcessing() {
			if (!this.ocrAvailable) return
			const cardId = this.cardId
			const projectId = this.projectId
			for (const attachment of this.attachments) {
				if (!this.showOcrForAttachment(attachment)) continue
				const fileId = this.attachmentFileId(attachment)
				const key = String(fileId)
				if (Object.prototype.hasOwnProperty.call(this.processingByFileId, key) || this.ocrBusyByFileId[key]) continue
				this.$set(this.ocrBusyByFileId, key, true)
				try {
					const payload = await projectOcrApi.getFileProcessing(projectId, fileId)
					if (cardId !== this.cardId || projectId !== this.projectId) return
					this.$set(this.processingByFileId, key, payload?.processing ?? null)
				} catch (error) {
					console.error('Could not load attachment OCR status', error)
				} finally {
					this.$set(this.ocrBusyByFileId, key, false)
				}
			}
		},
		processingDocumentTypeLabel(fileId) {
			const typeId = Number(this.processingByFileId[String(fileId)]?.document_type_id)
			return this.documentTypes.find(type => Number(type.id) === typeId)?.name ?? t('deck', 'No type')
		},
		ocrStatus(fileId) {
			return this.processingByFileId[String(fileId)]?.ocr_status ?? 'none'
		},
		ocrStatusLabel(fileId) {
			const labels = {
				none: t('deck', 'Not processed'),
				pending: t('deck', 'Pending'),
				processing: t('deck', 'Processing'),
				done: t('deck', 'Ready'),
				aborted: t('deck', 'Needs review'),
				failed: t('deck', 'Failed'),
				stale: t('deck', 'Outdated'),
			}
			return labels[this.ocrStatus(fileId)] ?? labels.none
		},
		expectedFieldNames(fileId) {
			const typeId = Number(this.processingByFileId[String(fileId)]?.document_type_id)
			const fields = this.documentTypes.find(type => Number(type.id) === typeId)?.fields ?? []
			return fields.map(field => typeof field === 'string' ? field : field?.name || field?.label || field?.key).filter(Boolean)
		},
		extractedEntries(fileId) {
			const extracted = this.processingByFileId[String(fileId)]?.extracted ?? {}
			const keys = [...new Set([...this.expectedFieldNames(fileId), ...Object.keys(extracted)])]
			return keys.map((key) => {
				const item = extracted[key] && typeof extracted[key] === 'object' ? extracted[key] : {}
				const value = item.value ?? ''
				return { key, name: item.name || item.label || key, value, missing: String(value).trim() === '' }
			})
		},
		canOpenExtractedData(fileId) {
			return this.extractedEntries(fileId).length > 0
		},
		canReprocess(fileId) {
			const record = this.processingByFileId[String(fileId)]
			return !this.isReadOnly && !!record?.document_type_id && record.ocr_status !== 'processing'
		},
		openExtractedData(fileId) {
			this.activeExtractedFileId = Number(fileId)
			this.activeExtractedDraft = Object.fromEntries(this.extractedEntries(fileId).map(entry => [entry.key, String(entry.value ?? '')]))
		},
		closeExtractedData() {
			if (this.savingExtracted) return
			this.activeExtractedFileId = null
			this.activeExtractedDraft = {}
		},
		async uploadSelectedFiles() {
			const documentTypeId = Number(this.uploadDocumentTypeId)
			if (!documentTypeId || this.selectedUploadFiles.length === 0) return
			this.ocrUploadBusy = true
			try {
				for (const file of this.selectedUploadFiles) {
					const uploaded = await this.uploadFileWithOcr(file, documentTypeId)
					if (!uploaded) {
						this.showOcrUploadModal = false
						this.selectedUploadFiles = []
						return
					}
				}
				this.showOcrUploadModal = false
				this.uploadDocumentTypeId = ''
				this.selectedUploadFiles = []
			} finally {
				this.ocrUploadBusy = false
			}
		},
		async uploadFileWithOcr(file, documentTypeId) {
			if (this.maxUploadSize > 0 && file.size > this.maxUploadSize) {
				showError(t('deck', 'Failed to upload {name}', { name: file.name }))
				return false
			}
			this.$set(this.uploadQueue, file.name, { name: file.name, progress: 0 })
			try {
				const payload = await projectOcrApi.uploadCardAttachment(this.projectId, this.cardId, documentTypeId, file, (event) => {
					this.$set(this.uploadQueue[file.name], 'progress', event.total ? Math.round(event.loaded * 100 / event.total) : 0)
				})
				if (payload?.attachment) {
					await this.$store.dispatch('registerAttachment', { cardId: this.cardId, attachment: payload.attachment })
					this.$set(this.processingByFileId, String(this.attachmentFileId(payload.attachment)), payload.processing)
					return true
				}
				return false
			} catch (error) {
				const payload = error?.response?.data ?? {}
				const processing = payload.processing
				if (processing?.file_id) {
					const key = String(processing.file_id)
					this.$set(this.processingByFileId, key, processing)
					this.$set(this.pendingAttachmentByFileId, key, Number(processing.id))
					this.openExtractedData(processing.file_id)
				}
				showError(payload.message || t('deck', 'Failed to upload {name}', { name: file.name }))
				return false
			} finally {
				this.$delete(this.uploadQueue, file.name)
			}
		},
		async reprocessAttachment(attachment) {
			const fileId = this.attachmentFileId(attachment)
			const key = String(fileId)
			this.$set(this.ocrBusyByFileId, key, true)
			try {
				const payload = await projectOcrApi.reprocessFileProcessing(this.projectId, fileId)
				this.$set(this.processingByFileId, key, payload?.processing ?? null)
			} catch (error) {
				showError(error?.response?.data?.message || t('deck', 'Could not reprocess this file.'))
			} finally {
				this.$set(this.ocrBusyByFileId, key, false)
			}
		},
		async saveExtractedData() {
			if (this.isReadOnly) return
			const fileId = Number(this.activeExtractedFileId)
			this.savingExtracted = true
			try {
				const processingId = this.pendingAttachmentByFileId[String(fileId)]
				const payload = processingId
					? await projectOcrApi.finalizeCardAttachment(this.projectId, this.cardId, processingId, this.activeExtractedDraft)
					: await projectOcrApi.updateFileExtractedFields(this.projectId, fileId, this.activeExtractedDraft)
				if (payload?.attachment) {
					await this.$store.dispatch('registerAttachment', { cardId: this.cardId, attachment: payload.attachment })
					this.$delete(this.pendingAttachmentByFileId, String(fileId))
				}
				const nextFileId = Number(payload?.processing?.file_id ?? fileId)
				if (nextFileId !== fileId) {
					this.$delete(this.processingByFileId, String(fileId))
				}
				this.$set(this.processingByFileId, String(nextFileId), payload?.processing ?? null)
				this.activeExtractedFileId = null
				this.activeExtractedDraft = {}
			} catch (error) {
				const payload = error?.response?.data ?? {}
				if (payload.processing) this.$set(this.processingByFileId, String(fileId), payload.processing)
				showError(payload.message || t('deck', 'Could not save extracted fields.'))
			} finally {
				this.savingExtracted = false
			}
		},
		shareFromFiles() {
			picker.pick()
				.then(async (path) => {
					console.debug(`path ${path} selected for sharing`)
					if (!path.startsWith('/')) {
						throw new Error(t('files', 'Invalid path selected'))
					}

					axios.post(generateOcsUrl('apps/files_sharing/api/v1/shares'), {
						path,
						shareType: 12,
						shareWith: '' + this.cardId,
					}).then(() => {
						this.fetchAttachments(this.cardId)
					})
				})
		},
		unshareAttachment(attachment) {
			this.$store.dispatch('unshareAttachment', attachment)
		},
		clickAddNewAttachmment() {
			this.$refs.localAttachments.click()
		},
		showViewer(attachment) {
			if (attachment.extendedData.fileid && window.OCA.Viewer.availableHandlers.map(handler => handler.mimes).flat().includes(attachment.extendedData.mimetype)) {
				// Hide the sidebar if opening card in modal to avoid wrong sidebar position calculating in Viewer app
				const sidebar = document.querySelector('aside.app-sidebar')
				if (sidebar && this.cardDetailsInModal) {
					sidebar.style.display = 'none'
				}
				const onClose = () => {
					if (sidebar && sidebar.style.display === 'none') {
						sidebar.style.display = ''
					}
				}
				window.OCA.Viewer.open({ path: attachment.extendedData.path, onClose })
				return
			}

			if (attachment.extendedData.fileid) {
				window.location = generateUrl('/f/' + attachment.extendedData.fileid)
				return
			}

			window.location = generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
		},
	},
}
</script>

<style lang="scss" scoped>

	.drop-upload--sidebar {
		min-height: 100%;
	}

	.button-group {
		display: flex;
		gap: calc(var(--default-grid-baseline) * 3);

		.icon-upload, .icon-folder {
			padding-inline-start: var(--default-clickable-area);
			background-position: 16px center;
			flex-grow: 1;
			height: var(--default-clickable-area);
			margin-bottom: 12px;
			text-align: start;
		}
	}

	.attachment__ocr {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		gap: 6px;
		margin-top: 6px;
	}

	.attachment__ocr-type,
	.attachment__ocr-status {
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
		padding: 3px 7px;
		font-size: 12px;
	}

	.attachment__ocr-status--done {
		color: var(--color-element-success, var(--color-success-text));
		background: rgba(var(--color-success-rgb), .15);
	}

	.attachment__ocr-status--failed,
	.attachment__ocr-status--aborted {
		color: var(--color-element-error, var(--color-error-text));
		background: rgba(var(--color-error-rgb), .15);
	}

	.attachment__ocr-status--pending,
	.attachment__ocr-status--processing,
	.attachment__ocr-status--stale {
		color: var(--color-element-warning, var(--color-warning-text));
		background: rgba(var(--color-warning-rgb), .15);
	}

	.attachment__ocr-modal {
		display: flex;
		flex-direction: column;
		gap: 12px;
		min-width: min(520px, 90vw);
		padding: 16px;

		select,
		input {
			width: 100%;
		}
	}

	.attachment__ocr-field {
		display: grid;
		grid-template-columns: minmax(120px, 1fr) 2fr;
		align-items: center;
		gap: 12px;
	}

	.attachment__ocr-input--missing {
		border-color: var(--color-warning);
	}

	.attachment__ocr-actions {
		display: flex;
		justify-content: flex-end;
		gap: 8px;
	}

	.attachment-list {
		&.selector {
			padding: 10px;
			position: absolute;
			width: 30%;
			max-width: 500px;
			min-width: 200px;
			max-height: 50%;
			top: 50%;
			inset-inline-start: 50%;
			transform: translate(-50%, -50%);
			background-color: #eee;
			z-index: 2;
			border-radius: 3px;
			box-shadow: 0 0 3px darkgray;
			overflow: scroll;
		}
		h3.attachment-selector {
			margin: 0 0 10px;
			padding: 0;
			.icon-close {
				display: inline-block;
				float: inline-end;
			}
		}

		li.attachment {
			display: flex;
			padding: 3px;
			min-height: var(--default-clickable-area);

			&.deleted {
				opacity: .5;
			}

			.fileicon {
				display: inline-block;
				min-width: 32px;
				width: 32px;
				height: 32px;
				background-size: contain;
			}
			.details {
				flex-grow: 1;
				flex-shrink: 1;
				min-width: 0;
				flex-basis: 50%;
				line-height: 110%;
				padding: 2px;
			}
			.filename {
				width: 70%;
				display: flex;
				.basename {
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
					padding-bottom: 2px;
				}
				.extension {
					opacity: 0.7;
				}
			}
			.attachment--info,
			.filesize, .filedate {
				font-size: 90%;
				color: var(--color-text-maxcontrast);
			}
			.app-popover-menu-utils {
				position: relative;
				inset-inline-end: -10px;
				button {
					height: 32px;
					width: 42px;
				}
			}
			button.icon-history {
				width: var(--default-clickable-area);
			}
			progress {
				margin-top: 3px;
			}
		}
	}

</style>
