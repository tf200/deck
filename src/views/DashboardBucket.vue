<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<div class="dashboard-summary">
			<span class="total-count">{{ t('deck', 'Total: {count}', { count: cards.length }) }}</span>
		</div>
		<NcDashboardWidget :items="items"
			empty-content-icon="icon-deck"
			:empty-content-message="emptyContentMessage"
			:show-more-text="showMoreText"
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
	</div>
</template>

<script>
import { NcDashboardWidget } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import { mapGetters } from 'vuex'
import Card from '../components/dashboard/Card.vue'
import { groupCardsByProject, projectGroupsToItems } from '../helpers/projectGroups.js'

export default {
	name: 'DashboardBucket',
	components: {
		NcDashboardWidget,
		Card,
	},
	props: {
		bucket: {
			type: String,
			required: true,
		},
		emptyContentMessage: {
			type: String,
			required: true,
		},
		showMoreText: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			loading: false,
		}
	},
	computed: {
		...mapGetters([
			'assignedCardsDashboard',
			'projectsByBoard',
		]),
		cards() {
			const cards = [...this.assignedCardsDashboard[this.bucket] || []]
			return cards.sort((a, b) => {
				if (a.duedate === null) {
					return a.title.localeCompare(b.title)
				}
				return new Date(a.duedate) - new Date(b.duedate)
			})
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
}
</script>

<style lang="scss" scoped>
	.dashboard-summary {
		display: flex;
		justify-content: flex-end;
		padding: 0 8px 8px;
	}

	.total-count {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 30px;
		height: 30px;
		padding: 0 8px;
		border-radius: var(--border-radius-pill);
		background: var(--color-background-dark);
		font-weight: 600;
	}

	.project-group-title {
		margin: 8px 8px 0;
		font-size: var(--default-font-size);
	}
</style>
