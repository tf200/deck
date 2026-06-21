<template>
	<div class="private-notes-tab">
		<div class="notes-header">
			<span class="notes-info-text">
				<LockIcon :size="16" class="lock-icon" />
				{{ t('deck', 'Private Notes') }}
			</span>
			<NcButton type="primary" class="add-note-btn" @click="openAddModal">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('deck', 'Add note') }}
			</NcButton>
		</div>

		<ul v-if="notes.length > 0" class="private-notes-list">
			<li v-for="note in notes" :key="note.id" class="private-note-item">
				<div class="note-item-header">
					<span class="note-date">{{ relativeDate(note.createdAt * 1000) }}</span>
					<NcActions :force-menu="true">
						<NcActionButton icon="icon-rename" @click="openEditModal(note)">
							{{ t('deck', 'Edit') }}
						</NcActionButton>
						<NcActionButton icon="icon-delete" @click="deleteNote(note.id)">
							{{ t('deck', 'Delete') }}
						</NcActionButton>
					</NcActions>
				</div>
				<div class="note-content">
					<NcRichText :text="note.text"
						:autolink="true"
						use-markdown />
				</div>
			</li>
		</ul>
		<div v-else class="empty-notes">
			<p>{{ t('deck', 'No private notes yet. Click the button above to add one!') }}</p>
		</div>

		<NcDialog :open.sync="showModal"
			:name="editingNoteId ? t('deck', 'Edit private note') : t('deck', 'Add private note')"
			size="large"
			@close="closeModal">
			<div class="private-note-dialog-content">
				<div v-if="textAppAvailable" class="modal-editor-wrapper">
					<div ref="modalEditor" />
				</div>
				<template v-else>
					<textarea v-model="modalText" class="fallback-textarea" placeholder="Write a private note..." />
				</template>
			</div>
			<template #actions>
				<NcButton @click="closeModal">
					{{ t('deck', 'Cancel') }}
				</NcButton>
				<NcButton type="primary" :disabled="!hasContent" @click="saveModalNote">
					{{ t('deck', 'Save') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import { NcButton, NcActions, NcActionButton, NcDialog, NcRichText } from '@nextcloud/vue'
import LockIcon from 'vue-material-design-icons/LockOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import relativeDate from '../../mixins/relativeDate.js'

export default {
	name: 'CardSidebarTabPrivateNotes',
	components: {
		NcButton,
		NcActions,
		NcActionButton,
		NcDialog,
		NcRichText,
		LockIcon,
		PlusIcon,
	},
	mixins: [relativeDate],
	props: {
		card: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			textAppAvailable: !!window.OCA?.Text?.createEditor,
			editor: null,
			showModal: false,
			modalText: '',
			editingNoteId: null,
		}
	},
	computed: {
		...mapGetters(['getNotesForCard']),
		notes() {
			return this.getNotesForCard(this.card.id)
		},
		hasContent() {
			return this.modalText.trim().length > 0
		},
	},
	watch: {
		card: {
			immediate: true,
			async handler() {
				await this.loadNotes()
			},
		},
		showModal(newVal) {
			if (newVal) {
				this.$nextTick(async () => {
					await this.setupEditor()
				})
			} else {
				this.destroyEditor()
			}
		},
	},
	methods: {
		async loadNotes() {
			try {
				await this.$store.dispatch('fetchNotes', this.card.id)
			} catch (e) {
				console.error('Could not load private notes', e)
			}
		},
		openAddModal() {
			this.editingNoteId = null
			this.modalText = ''
			this.showModal = true
		},
		openEditModal(note) {
			this.editingNoteId = note.id
			this.modalText = note.text
			this.showModal = true
		},
		async setupEditor() {
			this.destroyEditor()
			this.editor = await window.OCA.Text.createEditor({
				el: this.$refs.modalEditor,
				content: this.modalText,
				placeholder: t('deck', 'Write a private note …'),
				onUpdate: ({ markdown }) => {
					this.modalText = markdown
				},
			})
		},
		destroyEditor() {
			if (this.editor) {
				this.editor.destroy()
				this.editor = null
			}
		},
		closeModal() {
			this.showModal = false
			this.modalText = ''
			this.editingNoteId = null
		},
		async saveModalNote() {
			try {
				if (this.editingNoteId) {
					await this.$store.dispatch('updateNote', {
						cardId: this.card.id,
						noteId: this.editingNoteId,
						text: this.modalText,
					})
				} else {
					await this.$store.dispatch('createNote', {
						cardId: this.card.id,
						text: this.modalText,
					})
				}
				this.closeModal()
			} catch (e) {
				console.error('Could not save note', e)
			}
		},
		async deleteNote(noteId) {
			try {
				await this.$store.dispatch('deleteNote', {
					cardId: this.card.id,
					noteId,
				})
			} catch (e) {
				console.error('Could not delete note', e)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.private-notes-tab {
	display: flex;
	flex-direction: column;
	height: 100%;
	padding: 16px;
}

.notes-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
	color: var(--color-text-lighter);
}

.notes-info-text {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 0.95rem;
	font-weight: bold;

	.lock-icon {
		opacity: 0.7;
	}
}

.private-notes-list {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.private-note-item {
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background-color: var(--color-background-hover);

	.note-item-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		color: var(--color-text-lighter);
		font-size: 0.85rem;
		margin-bottom: 8px;
	}
}

.empty-notes {
	text-align: center;
	padding: 40px 20px;
	color: var(--color-text-lighter);
}

.private-note-dialog-content {
	width: 100%;
	min-height: 400px;
	box-sizing: border-box;
}

.modal-editor-wrapper {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
	padding: 8px;
	min-height: 400px;
	width: 100%;
	box-sizing: border-box;
	overflow-x: hidden;

	&:deep(.editor__content) {
		min-height: 380px;
		max-width: 100%;
		overflow-x: hidden;
	}
}

.fallback-textarea {
	width: 100%;
	min-height: 350px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 8px;
	resize: vertical;
	box-sizing: border-box;
}
</style>
