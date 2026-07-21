<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcDashboardWidget :items="items"
			empty-content-icon="icon-deck"
			:empty-content-message="t('deck', 'No upcoming cards')"
			:show-more-text="t('deck', 'upcoming cards')"
			:loading="loading"
			@hide="() => {}"
			@markDone="() => {}">
			<template #default="{ item }">
				<h4 v-if="item.isProjectGroup" class="project-group-title">
					{{ item.name }}
				</h4>
				<Card v-else :card="item" />
			</template>
		</NcDashboardWidget>
		<div class="center-button">
			<NcButton @click="toggleAddCardModel">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('deck', 'New card') }}
			</NcButton>
			<NcModal v-if="showAddCardModal" class="card-selector" @close="toggleAddCardModel">
				<CreateNewCardCustomPicker show-created-notice @cancel="toggleAddCardModel" />
			</NcModal>
		</div>
	</div>
</template>

<script>
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { NcButton, NcDashboardWidget, NcModal } from '@nextcloud/vue'
import { mapGetters } from 'vuex'
import Card from '../components/dashboard/Card.vue'
import { generateUrl } from '@nextcloud/router'
import CreateNewCardCustomPicker from './CreateNewCardCustomPicker.vue'
import { groupCardsByProject, projectGroupsToItems } from '../helpers/projectGroups.js'

export default {
	name: 'DashboardUpcoming',
	components: {
		CreateNewCardCustomPicker,
		NcModal,
		NcDashboardWidget,
		NcButton,
		PlusIcon,
		Card,
	},
	data() {
		return {
			loading: false,
			showAddCardModal: false,
		}
	},
	computed: {
		...mapGetters([
			'assignedCardsDashboard',
			'projectsByBoard',
		]),
		cards() {
			const list = Object.values(this.assignedCardsDashboard).flat()
				.filter((card) => {
					return card.duedate !== null
				})
			list.sort((a, b) => {
				return (new Date(a.duedate)).getTime() - (new Date(b.duedate)).getTime()
			})
			return list.slice(0, 5)
		},
		items() {
			return projectGroupsToItems(groupCardsByProject(this.cards, this.projectsByBoard))
		},
		showMoreUrl() {
			return this.cards.length > 7 ? generateUrl('/apps/deck') : null
		},
	},
	beforeMount() {
		this.loading = true
		this.$store.dispatch('loadUpcoming').then(() => {
			this.loading = false
		})
	},
	methods: {
		toggleAddCardModel() {
			this.showAddCardModal = !this.showAddCardModal
		},
	},
}
</script>

<style lang="scss" scoped>
	.center-button {
		display: flex;
		align-items: center;
		justify-content: center;
		margin-top: 10px;
	}

	#deck-widget-empty-content {
		text-align: center;
		margin-top: 5vh;
	}

	.project-group-title {
		margin: 8px 8px 0;
		font-size: var(--default-font-size);
	}

	.card {
		display: block;
		border-radius: var(--border-radius-large);
		padding: 5px 8px;
		height: 70px;
		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	.card--header {
		overflow: hidden;
		.title {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			display: block;
			position: relative;
			top: 3px;
		}
	}

	.labels {
		margin-inline-start: 0;
		margin-top: 3px;
	}

	.duedate:deep {
		.due {
			margin: 0 0 0 10px;
			padding: 0px 4px;
			font-size: 90%;
			margin-bottom: 7px;
		}
	}

	.right {
		float: inline-end;
	}
</style>
