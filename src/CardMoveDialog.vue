<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :open.sync="modalShow" :name="t('deck', 'Move/copy card')">
		<div class="modal__content">
			<NcSelect v-model="selectedBoard"
				:input-label="t('deck', 'Select a board')"
				:placeholder="t('deck', 'Select a board')"
				:options="activeBoards"
				:max-height="100"
				label="title"
				@option:selected="loadStacksFromBoard" />
			<NcSelect v-model="selectedStack"
				:disabled="stacksFromBoard.length === 0"
				:placeholder="stacksFromBoard.length === 0 ? t('deck', 'No lists available') : t('deck', 'Select a list')"
				:input-label="t('deck', 'Select a list')"
				:options="stacksFromBoard"
				:max-height="100"
				data-cy="select-stack"
				label="title" />
		</div>
		<template #actions>
			<NcButton :disabled="!isBoardAndStackChoosen || !canMoveToSelectedStack" type="secondary" @click="moveCard">
				{{ t('deck', 'Move card') }}
			</NcButton>
			<NcButton :disabled="!isBoardAndStackChoosen" type="primary" @click="cloneCard">
				{{ t('deck', 'Copy card') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcDialog, NcSelect, NcButton } from '@nextcloud/vue'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { mapGetters } from 'vuex'
import { canMoveCardToStack } from './utils/cardCapabilities.js'

export default {
	name: 'CardMoveDialog',
	components: { NcDialog, NcSelect, NcButton },
	data() {
		return {
			card: null,
			modalShow: false,
			selectedBoard: '',
			selectedStack: '',
			stacksFromBoard: [],
		}
	},
	computed: {
		...mapGetters(['stackById', 'boardById']),
		activeBoards() {
			return this.$store.getters.boards.filter((item) => item.deletedAt === 0 && item.archived === false)
		},
		isBoardAndStackChoosen() {
			return !(this.selectedBoard === '' || this.selectedStack === '')
		},
		canMoveToSelectedStack() {
			if (!this.card || !this.selectedStack) {
				return false
			}

			const sourceStack = this.stackById(this.card.stackId)
			const currentBoard = this.$store.state.currentBoard
			const sourceBoard = Number(currentBoard?.id) === Number(sourceStack?.boardId)
				? currentBoard
				: this.boardById(sourceStack?.boardId)
			return canMoveCardToStack(this.card, sourceBoard, this.selectedStack.id)
		},
	},
	watch: {
		selectedBoard: {
			immediate: true,
			handler() {
				this.selectedStack = ''
				this.stacksFromBoard = []
			},
		},
	},
	mounted() {
		subscribe('deck:card:show-move-dialog', this.openModal)
	},
	destroyed() {
		unsubscribe('deck:card:show-move-dialog', this.openModal)
	},
	methods: {
		openModal(card) {
			this.card = card
			this.selectedStack = this.stackById(this.card.stackId)
			this.selectedBoard = this.boardById(this.selectedStack.boardId)
			this.loadStacksFromBoard(this.selectedBoard)
			this.modalShow = true
		},
		async loadStacksFromBoard(board) {
			try {
				const url = generateOcsUrl(`/apps/deck/api/v1.0/stacks/${board.id}`)
				const response = await axios.get(url)
				this.stacksFromBoard = response.data.ocs.data
			} catch (err) {
				return err
			}
		},
		async moveCard() {
			this.copiedCard = Object.assign({}, this.card)
			this.copiedCard.stackId = this.selectedStack.id
			const sourceBoardId = this.stackById(this.card.stackId).boardId
			const updatedCard = await this.$store.dispatch('moveCard', { card: this.copiedCard, oldBoardId: sourceBoardId })
			if (parseInt(sourceBoardId) === parseInt(this.selectedStack.boardId)) {
				this.$store.commit('addNewCard', updatedCard)
			}
			this.modalShow = false
		},
		async cloneCard() {
			this.$store.dispatch('cloneCard', { cardId: this.card.id, targetStackId: this.selectedStack.id })
			this.modalShow = false
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	.select {
		margin-bottom: 12px;
	}
}
</style>
