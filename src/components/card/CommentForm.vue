<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="comment-form">
		<NcRichContenteditable v-model="commentText"
			:auto-complete="autoComplete"
			:maxlength="1000"
			:user-data="members"
			@submit="submit" />
		<label class="comment-form__type-label">
			<span>{{ t('deck', 'Type') }}</span>
			<select v-model="selectedNoteType" class="comment-form__type-select">
				<option v-for="type in noteTypeOptions" :key="type.value" :value="type.value">
					{{ noteTypeLabel(type.value) }}
				</option>
			</select>
		</label>
		<NcButton v-show="hasContent"
			type="tertiary"
			:aria-label="t('deck', 'Submit')"
			:title="t('deck', 'Submit')"
			class="comment-form__submit"
			@click="submit">
			<template #icon>
				<ArrowRightIcon :size="20" />
			</template>
		</NcButton>
		<p v-if="error">
			{{ error }}
		</p>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import { NcButton, NcRichContenteditable } from '@nextcloud/vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import { COMMENT_TYPES, DEFAULT_COMMENT_TYPE, commentTypeLabel } from '../../constants/comment-types.js'

export default {
	name: 'CommentForm',
	components: {
		ArrowRightIcon,
		NcButton,
		NcRichContenteditable,
	},
	props: {
		value: {
			type: String,
			default: '',
		},
		noteType: {
			type: String,
			default: DEFAULT_COMMENT_TYPE,
		},
	},
	data() {
		return {
			commentText: this.value,
			selectedNoteType: this.noteType,
			error: null,
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		members() {
			const obj = {}
			this.currentBoard.users.forEach(user => {
				obj[user.uid] = {
					icon: 'icon-user',
					id: user.uid,
					label: user.displayname,
					source: 'users',
				}
			})
			return obj
		},
		noteTypeOptions() {
			return COMMENT_TYPES
		},
		hasContent() {
			return this.commentText.trim().length > 0
		},
	},
	watch: {
		value(val) {
			this.commentText = val
		},
		noteType(val) {
			this.selectedNoteType = val || DEFAULT_COMMENT_TYPE
		},
		selectedNoteType(val) {
			this.$emit('update:noteType', val)
		},
	},
	methods: {
		noteTypeLabel: commentTypeLabel,
		autoComplete(search, callback) {
			callback(Object.values(this.members))
		},
		validate(submit) {
			this.error = null
			const content = this.commentText
			if (submit && content.length === 0) {
				this.error = t('deck', 'The comment cannot be empty.')
			}
			if (content.length > 1000) {
				this.error = t('deck', 'The comment cannot be longer than 1000 characters.')
			}
			return this.error === null ? content : null
		},
		submit() {
			const content = this.validate(true)
			if (content) {
				// We need the plain text representation for the input event as otherwise it will propagate back to the contenteditable
				// The input event is only used for change detection to make sure that the input is reset after posting the comment
				const temp = document.createElement('div')
				temp.innerHTML = content
				const text = temp.textContent || temp.innerText || ''
				this.$emit('input', text)
				this.$emit('submit', text, this.selectedNoteType)
			}
		},
	},
}
</script>

<style lang="scss">
	@import '../../css/comments.scss';

	[class^="_tribute-container-autocomplete_"],
	[class*=" _tribute-container-autocomplete_"],
	[class*="_tribute-container-autocomplete_"] {
		z-index: 9999 !important;
	}

	.comment-form {
		position: relative;

		.comment-form__submit {
			position: absolute;
			bottom: var(--default-grid-baseline);
			inset-inline-end: var(--default-grid-baseline);
			z-index: 1;
		}

		// Add padding to prevent text from going under the button
		:deep(.rich-content-editor__input) {
			padding-inline-end: calc(var(--default-clickable-area) + var(--default-grid-baseline));
		}
	}

	.comment-form__type-label {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-top: 8px;
		color: var(--color-text-lighter);
		font-size: 12px;
	}

	.comment-form__type-select {
		min-height: 34px;
		padding: 0 8px;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius);
		background: var(--color-main-background);
		color: var(--color-main-text);
		font-size: 12px;
	}
</style>
