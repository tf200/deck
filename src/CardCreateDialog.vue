<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal class="card-selector" @close="close">
		<CreateNewCardCustomPicker :title="title"
			:description="description"
			:board-id="boardId"
			show-created-notice
			@cancel="close"
			@close="close" />
	</NcModal>
</template>

<script>
import { NcModal } from '@nextcloud/vue'
import CreateNewCardCustomPicker from './views/CreateNewCardCustomPicker.vue'

export default {
	name: 'CardCreateDialog',
	components: {
		NcModal,
		CreateNewCardCustomPicker,
	},
	props: {
		boardId: {
			type: [Number, String],
			default: null,
		},
		title: {
			type: String,
			default: '',
		},
		description: {
			type: String,
			default: '',
		},
	},
}
</script>
