<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="hasProject"
		class="project-member-access"
		:class="{ 'project-member-access--expanded': isExpanded }">
		<!-- HEADER STRIP -->
		<div class="project-member-access__header"
			role="button"
			tabindex="0"
			:aria-expanded="isExpanded ? 'true' : 'false'"
			@click="toggleExpand"
			@keydown.enter.prevent="toggleExpand"
			@keydown.space.prevent="toggleExpand">
			<div class="project-member-access__title-group">
				<AccountGroup :size="20" class="project-member-access__title-icon" />
				<span class="project-member-access__title">Permissions Overview</span>

				<!-- EXECUTIVE SUMMARY BADGES -->
				<div v-if="summary" class="project-member-access__pills">
					<span class="access-pill access-pill--neutral">
						{{ totalMembersCount }} {{ totalMembersCount === 1 ? 'Member' : 'Members' }}
					</span>
					<span class="access-pill access-pill--success">
						{{ fullAccessCount }} Full Edit
					</span>
					<span class="access-pill access-pill--warning">
						{{ readOnlyAccessCount }} Read Only
					</span>
					<span v-if="deniedAccessCount > 0" class="access-pill access-pill--danger">
						{{ deniedAccessCount }} Denied
					</span>
				</div>
				<span v-else-if="loading" class="project-member-access__loading-text">
					Loading permissions...
				</span>
			</div>

			<div class="project-member-access__toggle-action">
				<NcButton type="tertiary"
					size="small"
					class="project-member-access__toggle-btn"
					:aria-expanded="isExpanded ? 'true' : 'false'"
					@click.stop="toggleExpand">
					<template #icon>
						<ChevronUp v-if="isExpanded" :size="18" />
						<ChevronDown v-else :size="18" />
					</template>
					{{ isExpanded ? 'Collapse' : 'Expand' }}
				</NcButton>
			</div>
		</div>

		<!-- EXPANDED BODY -->
		<div v-if="isExpanded" class="project-member-access__body">
			<div v-if="loading && !summary" class="project-member-access__state" role="status">
				<NcLoadingIcon :size="24" />
				<span>Loading permissions overview...</span>
			</div>

			<div v-else-if="error" class="project-member-access__state project-member-access__state--error" role="alert">
				<strong>Permissions overview could not be loaded.</strong>
				<span>{{ error }}</span>
				<NcButton type="secondary" @click.stop="loadSummary">
					Retry
				</NcButton>
			</div>

			<template v-else-if="summary">
				<!-- SEARCH & FILTER TOOLBAR -->
				<div class="project-member-access__toolbar">
					<div class="project-member-access__search">
						<Magnify :size="16" class="project-member-access__search-icon" />
						<input v-model.trim="searchQuery"
							type="search"
							placeholder="Search members by name..."
							class="project-member-access__search-input"
							aria-label="Search members by name"
							@click.stop>
					</div>
					<div class="project-member-access__filters" @click.stop>
						<select v-model="accessFilter"
							class="project-member-access__select"
							aria-label="Filter members by access level">
							<option value="all">
								All Access Levels ({{ totalMembersCount }})
							</option>
							<option value="edit">
								Full Edit Access ({{ fullAccessCount }})
							</option>
							<option value="read">
								Read Only Access ({{ readOnlyAccessCount }})
							</option>
							<option value="denied">
								Access Denied ({{ deniedAccessCount }})
							</option>
						</select>
						<span class="project-member-access__count-badge">
							Showing {{ filteredMemberRows.length }} of {{ totalMembersCount }} members
						</span>
					</div>
				</div>

				<!-- EMPTY FILTER STATE -->
				<div v-if="filteredMemberRows.length === 0" class="project-member-access__state">
					<strong>No members match your filter</strong>
					<span>Try searching for a different member name or changing the access filter.</span>
				</div>

				<!-- MEMBER CARDS LIST -->
				<div v-else class="project-member-access__members">
					<article v-for="member in filteredMemberRows" :key="member.id" class="project-member-access__member">
						<div class="project-member-access__person">
							<NcAvatar :user="member.id"
								:display-name="member.displayName"
								:size="38" />
							<div class="project-member-access__identity">
								<div class="project-member-access__name-line">
									<h5>{{ member.displayName }}</h5>
									<span v-if="member.isOwner" class="project-member-access__owner">Owner</span>
								</div>
								<div class="project-member-access__roles">
									<span v-for="role in member.drasciRoles"
										:key="role.key"
										class="project-member-access__role project-member-access__role--drasci">
										DRASCIVS: {{ role.label }}
									</span>
									<span v-for="role in member.functionalRoles"
										:key="role.key"
										class="project-member-access__role">
										{{ role.label }}
									</span>
									<span v-if="member.functionalRoles.length === 0" class="project-member-access__role project-member-access__role--empty">
										No functional role
									</span>
								</div>
							</div>
							<div class="project-member-access__board-state"
								:class="`project-member-access__board-state--${member.boardAccessState}`">
								<span class="project-member-access__status-dot" />
								{{ member.boardAccessLabel }}
							</div>
						</div>

						<div class="project-member-access__actions">
							<div v-for="action in member.actionRows"
								:key="action.key"
								class="project-member-access__action"
								:class="`project-member-access__action--${action.state}`">
								<div class="project-member-access__action-heading">
									<div class="project-member-access__action-title-wrap">
										<component :is="actionIcon(action.key)" :size="15" class="project-member-access__action-icon" />
										<strong>{{ action.label }}</strong>
									</div>
									<span class="project-member-access__action-status">{{ action.statusLabel }}</span>
								</div>
								<button v-if="action.allowedCards.length > 0"
									type="button"
									class="project-member-access__details-toggle"
									:aria-expanded="isCardExpanded(member.id, action.key) ? 'true' : 'false'"
									@click.stop="toggleCards(member.id, action.key)">
									{{ isCardExpanded(member.id, action.key) ? 'Hide cards' : 'Show cards' }}
									<ChevronDown :size="15"
										:class="{ 'project-member-access__chevron--open': isCardExpanded(member.id, action.key) }" />
								</button>
								<ul v-if="isCardExpanded(member.id, action.key)" class="project-member-access__card-list">
									<li v-for="card in action.allowedCards" :key="card.id">
										{{ card.title }}
									</li>
								</ul>
							</div>
						</div>
					</article>
				</div>
			</template>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { NcAvatar, NcButton, NcLoadingIcon } from '@nextcloud/vue'

import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import SwapHorizontal from 'vue-material-design-icons/SwapHorizontal.vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import Pen from 'vue-material-design-icons/Pen.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'

const ACTIONS = [
	{ key: 'view', label: 'View' },
	{ key: 'move', label: 'Move' },
	{ key: 'verify', label: 'Verify' },
	{ key: 'sign', label: 'Sign' },
]

export default {
	name: 'ProjectMemberAccessSummary',
	components: {
		AccountGroup,
		ChevronDown,
		ChevronUp,
		NcAvatar,
		NcButton,
		NcLoadingIcon,
		Eye,
		SwapHorizontal,
		CheckCircle,
		Pen,
		Magnify,
	},
	props: {
		boardId: {
			type: [String, Number],
			required: true,
		},
		projectId: {
			type: [String, Number],
			default: null,
		},
	},
	data() {
		return {
			isExpanded: true,
			resolvedProjectId: null,
			summary: null,
			loading: false,
			error: '',
			expandedActions: {},
			searchQuery: '',
			accessFilter: 'all',
			hasProject: true,
			initRequestId: 0,
		}
	},
	computed: {
		effectiveProjectId() {
			const propId = Number(this.projectId)
			if (Number.isFinite(propId) && propId > 0) {
				return propId
			}
			return this.resolvedProjectId
		},
		totalCards() {
			return Math.max(0, Number(this.summary?.totalCards) || 0)
		},
		totalMembersCount() {
			return this.memberRows.length
		},
		fullAccessCount() {
			return this.memberRows.filter(m => m.boardAccess === 'edit').length
		},
		readOnlyAccessCount() {
			return this.memberRows.filter(m => m.boardAccess === 'read').length
		},
		deniedAccessCount() {
			return this.memberRows.filter(m => !m.hasBoardAccess || m.boardAccess === 'none').length
		},
		filteredMemberRows() {
			let list = this.memberRows
			if (this.searchQuery.trim()) {
				const q = this.searchQuery.toLowerCase().trim()
				list = list.filter(m => m.displayName.toLowerCase().includes(q) || m.id.toLowerCase().includes(q))
			}
			if (this.accessFilter !== 'all') {
				list = list.filter(m => {
					if (this.accessFilter === 'edit') return m.boardAccess === 'edit'
					if (this.accessFilter === 'read') return m.boardAccess === 'read'
					if (this.accessFilter === 'denied') return !m.hasBoardAccess || m.boardAccess === 'none'
					return true
				})
			}
			return list
		},
		memberRows() {
			const members = Array.isArray(this.summary?.members) ? this.summary.members : []
			return members.map(member => {
				const boardAccess = String(member.boardAccess || 'none')
				const hasBoardAccess = boardAccess !== 'none'
				const drasciRoleKeys = Array.isArray(member.drascivsRoles)
					? member.drascivsRoles
					: (Array.isArray(member.drasciRoles)
						? member.drasciRoles
						: (member.drasciRole ? [member.drasciRole] : []))
				const drasciRoleLabels = Array.isArray(member.drascivsRoleLabels)
					? member.drascivsRoleLabels
					: (Array.isArray(member.drasciRoleLabels)
						? member.drasciRoleLabels
						: (member.drasciRoleLabel ? [member.drasciRoleLabel] : drasciRoleKeys))
				const drasciRoles = drasciRoleLabels.map((label, index) => ({
					key: drasciRoleKeys[index] || `${label}:${index}`,
					label,
				}))
				const functionalRoleKeys = Array.isArray(member.functionalRoleKeys) ? member.functionalRoleKeys : []
				const functionalRoleLabels = Array.isArray(member.functionalRoleLabels) ? member.functionalRoleLabels : []
				const functionalRoles = functionalRoleLabels
					.map((label, index) => ({
						key: functionalRoleKeys[index] || `${label}:${index}`,
						label,
					}))
					.filter(role => role.label)

				const boardAccessState = boardAccess === 'edit' ? 'edit' : (boardAccess === 'read' ? 'read' : 'denied')
				const boardAccessLabel = boardAccess === 'edit'
					? 'Full edit access'
					: (boardAccess === 'read' ? 'Read only' : 'Access denied')

				return {
					...member,
					id: String(member.id || ''),
					displayName: member.displayName || member.id || 'Unknown member',
					drasciRoles,
					functionalRoles,
					hasBoardAccess,
					boardAccessState,
					boardAccessLabel,
					actionRows: ACTIONS.map(action => this.buildActionRow(member, action, hasBoardAccess)),
				}
			})
		},
	},
	watch: {
		boardId: {
			immediate: true,
			async handler() {
				await this.initialize()
			},
		},
		projectId(newVal, oldVal) {
			if (newVal && newVal !== oldVal) {
				this.initialize()
			}
		},
	},
	methods: {
		actionIcon(key) {
			switch (key) {
			case 'view': return 'Eye'
			case 'move': return 'SwapHorizontal'
			case 'verify': return 'CheckCircle'
			case 'sign': return 'Pen'
			default: return 'Eye'
			}
		},
		toggleExpand() {
			this.isExpanded = !this.isExpanded
			if (this.isExpanded && !this.summary && !this.loading) {
				this.loadSummary()
			}
		},
		async initialize() {
			const reqId = ++this.initRequestId
			const propId = Number(this.projectId)
			if (Number.isFinite(propId) && propId > 0) {
				this.resolvedProjectId = propId
				this.hasProject = true
				await this.loadSummary(reqId)
				return
			}

			// If projectId not provided as prop, resolve from boardId
			const bId = Number(this.boardId)
			if (!Number.isFinite(bId) || bId <= 0) {
				this.hasProject = false
				return
			}

			this.loading = true
			try {
				const response = await axios.get(generateUrl(`/apps/projectcreatoraio/api/v1/projects/board/${bId}`), {
					headers: {
						'OCS-APIRequest': 'true',
						'Content-Type': 'application/json',
					},
				})
				if (reqId !== this.initRequestId) {
					return
				}
				const project = response?.data?.ocs?.data ?? response?.data
				if (project?.id) {
					this.resolvedProjectId = Number(project.id)
					this.hasProject = true
					await this.loadSummary(reqId)
				} else {
					this.hasProject = false
				}
			} catch (e) {
				if (reqId === this.initRequestId) {
					this.hasProject = false
				}
			} finally {
				if (reqId === this.initRequestId && !this.resolvedProjectId) {
					this.loading = false
				}
			}
		},
		async loadSummary(reqId = this.initRequestId) {
			if (!this.effectiveProjectId) {
				return
			}

			this.loading = true
			this.error = ''
			try {
				const response = await axios.get(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${this.effectiveProjectId}/deck-access-summary`), {
					headers: {
						'OCS-APIRequest': 'true',
						'Content-Type': 'application/json',
					},
				})
				if (reqId !== this.initRequestId) {
					return
				}
				const raw = response?.data?.ocs?.data ?? response?.data
				this.summary = (raw && typeof raw === 'object') ? raw : null
			} catch (e) {
				if (reqId === this.initRequestId) {
					this.error = 'Could not load project permissions overview.'
				}
			} finally {
				if (reqId === this.initRequestId) {
					this.loading = false
				}
			}
		},
		buildActionRow(member, actionDefinition, hasBoardAccess) {
			const action = member.actions?.[actionDefinition.key] || {}
			const status = String(action.status || 'none')
			const total = Math.max(0, Number(action.total) || 0)
			const allowed = Math.max(0, Number(action.allowed) || 0)
			const allowedCards = Array.isArray(action.allowedCards) ? action.allowedCards : []

			if (!hasBoardAccess) {
				return {
					...actionDefinition,
					state: 'denied',
					statusLabel: 'Access denied',
					allowedCards: [],
				}
			}
			if (status === 'all') {
				return {
					...actionDefinition,
					state: 'all',
					statusLabel: 'All cards',
					allowedCards,
				}
			}
			if (status === 'some') {
				return {
					...actionDefinition,
					state: 'some',
					statusLabel: `Some cards (${allowed}/${total})`,
					allowedCards,
				}
			}
			return {
				...actionDefinition,
				state: 'none',
				statusLabel: 'No cards',
				allowedCards: [],
			}
		},
		expansionKey(memberId, action) {
			return `${memberId}:${action}`
		},
		isCardExpanded(memberId, action) {
			return this.expandedActions[this.expansionKey(memberId, action)] === true
		},
		toggleCards(memberId, action) {
			const key = this.expansionKey(memberId, action)
			this.$set(this.expandedActions, key, !this.expandedActions[key])
		},
	},
}
</script>

<style scoped>
.project-member-access {
	margin: 20px 24px 20px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 12px);
	background: var(--color-main-background);
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
	overflow: hidden;
	transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.project-member-access:hover {
	border-color: var(--color-border-dark, var(--color-border));
}

/* HEADER STRIP */
.project-member-access__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 12px 18px;
	background: var(--color-background-hover);
	cursor: pointer;
	user-select: none;
	gap: 12px;
	border-bottom: 1px solid transparent;
	transition: background 0.15s ease, border-color 0.15s ease;
}

.project-member-access--expanded .project-member-access__header {
	border-bottom-color: var(--color-border);
}

.project-member-access__header:hover {
	background: var(--color-background-dark, rgba(0, 0, 0, 0.05));
}

.project-member-access__title-group {
	display: flex;
	align-items: center;
	gap: 12px;
	flex-wrap: wrap;
	min-width: 0;
}

.project-member-access__title-icon {
	color: var(--color-primary-element);
	flex-shrink: 0;
}

.project-member-access__title {
	font-weight: 700;
	font-size: 14px;
	color: var(--color-main-text);
	white-space: nowrap;
}

.project-member-access__pills {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	flex-wrap: wrap;
}

.access-pill {
	display: inline-flex;
	align-items: center;
	padding: 2px 9px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 600;
	white-space: nowrap;
	line-height: 1.3;
}

.access-pill--neutral {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	color: var(--color-main-text);
}

.access-pill--success {
	background: var(--color-success-light, rgba(70, 186, 97, 0.15));
	color: var(--color-success, #27ae60);
	border: 1px solid var(--color-success, #27ae60);
}

.access-pill--warning {
	background: var(--color-warning-light, rgba(230, 126, 34, 0.15));
	color: var(--color-warning, #d35400);
	border: 1px solid var(--color-warning, #d35400);
}

.access-pill--danger {
	background: var(--color-error-light, rgba(231, 76, 60, 0.15));
	color: var(--color-error, #c0392b);
	border: 1px solid var(--color-error, #c0392b);
}

.project-member-access__loading-text {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.project-member-access__toggle-action {
	display: flex;
	align-items: center;
	flex-shrink: 0;
}

/* EXPANDED DRAWER BODY */
.project-member-access__body {
	padding: 16px 18px 20px;
	background: var(--color-main-background);
	max-height: 520px;
	overflow-y: auto;
	scrollbar-gutter: stable;
}

/* SEARCH & FILTER TOOLBAR */
.project-member-access__toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 8px 12px;
	margin-bottom: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background: var(--color-background-hover);
	flex-wrap: wrap;
}

.project-member-access__search {
	position: relative;
	flex: 1;
	min-width: 200px;
	max-width: 360px;
	display: flex;
	align-items: center;
}

.project-member-access__search-icon {
	position: absolute;
	left: 10px;
	color: var(--color-text-maxcontrast);
	pointer-events: none;
}

.project-member-access__search-input {
	width: 100%;
	padding: 6px 12px 6px 32px;
	border: 1px solid var(--color-border);
	border-radius: 16px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 12.5px;
	box-sizing: border-box;
}

.project-member-access__search-input:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

.project-member-access__filters {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}

.project-member-access__select {
	padding: 6px 12px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 12px;
}

.project-member-access__count-badge {
	font-size: 11.5px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

/* MEMBER CARDS */
.project-member-access__members {
	display: grid;
	gap: 12px;
}

.project-member-access__member {
	padding: 14px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius, 8px);
	background: var(--color-main-background);
	transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.project-member-access__member:hover {
	border-color: var(--color-primary-element-light, var(--color-border));
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.project-member-access__person {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr) auto;
	align-items: center;
	gap: 12px;
}

.project-member-access__identity {
	min-width: 0;
}

.project-member-access__name-line {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.project-member-access__name-line h5 {
	margin: 0;
	font-size: 14.5px;
	font-weight: 650;
	overflow-wrap: anywhere;
}

.project-member-access__owner {
	padding: 1px 7px;
	border-radius: 999px;
	background: var(--color-primary-element-light, rgba(0, 130, 201, 0.15));
	color: var(--color-primary-element);
	font-size: 10px;
	font-weight: 700;
}

.project-member-access__roles {
	display: flex;
	gap: 6px;
	margin-top: 5px;
	flex-wrap: wrap;
}

.project-member-access__role {
	padding: 2px 8px;
	border: 1px solid var(--color-border);
	border-radius: 999px;
	font-size: 11px;
	color: var(--color-main-text);
	overflow-wrap: anywhere;
}

.project-member-access__role--drasci {
	border-color: var(--color-primary-element);
	color: var(--color-primary-element);
	font-weight: 600;
}

.project-member-access__role--empty {
	border-style: dashed;
	color: var(--color-text-maxcontrast);
}

.project-member-access__board-state {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 12px;
	font-weight: 600;
	white-space: nowrap;
}

.project-member-access__board-state--edit {
	color: var(--color-success, #27ae60);
}

.project-member-access__board-state--read {
	color: var(--color-warning, #d35400);
}

.project-member-access__board-state--denied {
	color: var(--color-error, #c0392b);
}

.project-member-access__status-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background: currentColor;
}

/* ACTIONS */
.project-member-access__actions {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 10px;
	margin-top: 12px;
}

.project-member-access__action {
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-top: 3px solid var(--color-text-maxcontrast);
	border-radius: 6px;
	background: var(--color-background-hover);
}

.project-member-access__action--all {
	border-top-color: var(--color-success, #27ae60);
}

.project-member-access__action--some {
	border-top-color: var(--color-warning, #d35400);
}

.project-member-access__action--denied {
	border-top-color: var(--color-error, #c0392b);
}

.project-member-access__action--none {
	border-top-color: var(--color-border-dark, #888);
}

.project-member-access__action-heading {
	display: grid;
	gap: 2px;
}

.project-member-access__action-title-wrap {
	display: flex;
	align-items: center;
	gap: 6px;
}

.project-member-access__action-icon {
	color: var(--color-primary-element);
	flex-shrink: 0;
}

.project-member-access__action-heading strong {
	font-size: 12.5px;
}

.project-member-access__action-status {
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	line-height: 1.3;
}

.project-member-access__details-toggle {
	display: inline-flex;
	align-items: center;
	gap: 3px;
	padding: 5px 0 0;
	border: 0;
	background: transparent;
	color: var(--color-primary-element);
	font-size: 11.5px;
	font-weight: 600;
	cursor: pointer;
}

.project-member-access__details-toggle svg {
	transition: transform 0.15s ease;
}

.project-member-access__chevron--open {
	transform: rotate(180deg);
}

.project-member-access__card-list {
	display: grid;
	gap: 4px;
	margin: 8px 0 0;
	padding-left: 16px;
	color: var(--color-main-text);
	font-size: 11.5px;
}

.project-member-access__card-list li {
	overflow-wrap: anywhere;
}

.project-member-access__state {
	display: flex;
	min-height: 120px;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 10px;
	padding: 24px;
	border: 1px dashed var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	color: var(--color-text-maxcontrast);
	text-align: center;
	font-size: 13px;
}

.project-member-access__state strong {
	color: var(--color-main-text);
}

.project-member-access__state--error {
	border-color: var(--color-error, #e74c3c);
}

@media (max-width: 900px) {
	.project-member-access {
		margin: 14px 16px 16px;
	}

	.project-member-access__actions {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (max-width: 600px) {
	.project-member-access {
		margin: 10px 10px 12px;
	}

	.project-member-access__person {
		grid-template-columns: auto minmax(0, 1fr);
		align-items: start;
	}

	.project-member-access__board-state {
		grid-column: 1 / -1;
	}

	.project-member-access__actions {
		grid-template-columns: 1fr;
	}
}
</style>
