<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDashboardWidget :items="items"
		empty-content-icon="icon-deck"
		:empty-content-message="t('deck', 'No upcoming cards')"
		:show-more-text="t('deck', 'upcoming cards today')"
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
</template>

<script>
import { NcDashboardWidget } from '@nextcloud/vue'
import { mapGetters } from 'vuex'
import Card from '../components/dashboard/Card.vue'
import { generateUrl } from '@nextcloud/router'
import { groupCardsByProject, projectGroupsToItems } from '../helpers/projectGroups.js'

export default {
	name: 'DashboardToday',
	components: {
		NcDashboardWidget,
		Card,
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
			const list = [...this.assignedCardsDashboard.today || []]
			list.sort((a, b) => {
				return (new Date(a.duedate)).getTime() - (new Date(b.duedate)).getTime()
			})
			return list
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
}
</script>

<style lang="scss" scoped>
	#deck-widget-empty-content {
		text-align: center;
		margin-top: 5vh;
	}

	.project-group-title {
		margin: 8px 8px 0;
		font-size: var(--default-font-size);
	}
</style>
