<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<div class="dashboard-controls">
			<div class="range-filter" role="group" :aria-label="t('deck', 'Upcoming card range')">
				<button v-for="filter in filters"
					:key="filter.days"
					type="button"
					class="range-filter__button"
					:class="{ 'range-filter__button--active': selectedDays === filter.days }"
					:aria-pressed="selectedDays === filter.days"
					:title="filter.ariaLabel"
					:aria-label="filter.ariaLabel"
					@click="selectedDays = filter.days">
					{{ filter.label }}
				</button>
			</div>
			<span class="total-count">{{ t('deck', 'Total: {count}', { count: totalCount }) }}</span>
		</div>
		<NcDashboardWidget :items="items"
			empty-content-icon="icon-deck"
			:empty-content-message="t('deck', 'No upcoming cards')"
			:show-more-text="t('deck', 'upcoming cards')"
			:show-more-url="showMoreUrl"
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
import { getUpcomingCards } from '../helpers/dashboardCards.js'

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
			selectedDays: 7,
		}
	},
	computed: {
		...mapGetters([
			'assignedCardsDashboard',
			'projectsByBoard',
		]),
		filters() {
			return [
				{
					days: 7,
					label: t('deck', '7d'),
					ariaLabel: t('deck', 'Next 7 days'),
				},
				{
					days: 30,
					label: t('deck', '30d'),
					ariaLabel: t('deck', 'Next 30 days'),
				},
				{
					days: 90,
					label: t('deck', '90d'),
					ariaLabel: t('deck', 'Next 90 days'),
				},
			]
		},
		cards() {
			return getUpcomingCards(this.assignedCardsDashboard, this.selectedDays)
		},
		totalCount() {
			return this.cards.length
		},
		items() {
			return projectGroupsToItems(groupCardsByProject(this.cards.slice(0, 5), this.projectsByBoard))
		},
		showMoreUrl() {
			return this.cards.length > 5 ? generateUrl('/apps/deck') : null
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
	.dashboard-controls {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: space-between;
		gap: 8px;
		padding: 0 8px 8px;
	}

	.range-filter {
		display: flex;
		flex: 1 1 auto;
		min-width: 0;
		padding: 2px;
		gap: 2px;
		border-radius: var(--border-radius-pill);
		background: var(--color-background-dark);
		box-sizing: border-box;
	}

	.range-filter__button {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		flex: 1 1 0%;
		min-width: 0;
		min-height: 30px;
		margin: 0;
		padding: 4px 8px;
		border: 0;
		border-radius: var(--border-radius-pill);
		background: transparent;
		color: var(--color-text-maxcontrast);
		font-size: var(--default-font-size-small, 0.85rem);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		box-sizing: border-box;
		cursor: pointer;

		&:hover,
		&:focus-visible {
			background: var(--color-background-hover);
			color: var(--color-main-text);
		}
	}

	.range-filter__button--active {
		background: var(--color-primary-element);
		color: var(--color-primary-element-text);
		font-weight: 600;

		&:hover,
		&:focus-visible {
			background: var(--color-primary-element-hover);
			color: var(--color-primary-element-text);
		}
	}

	.total-count {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		margin-inline-start: auto;
		min-width: 30px;
		height: 30px;
		padding: 0 8px;
		border-radius: var(--border-radius-pill);
		background: var(--color-background-dark);
		font-weight: 600;
		font-size: var(--default-font-size-small, 0.85rem);
		white-space: nowrap;
		box-sizing: border-box;
	}

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
