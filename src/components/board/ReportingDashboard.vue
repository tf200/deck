<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="reporting-dashboard"
		:class="{
			'reporting-dashboard--inline': inline,
			'reporting-dashboard--expanded': isExpanded,
		}">
		<!-- COLLAPSIBLE HEADER (for inline board mode) -->
		<div v-if="inline"
			class="reporting-dashboard__header"
			role="button"
			tabindex="0"
			:aria-expanded="isExpanded ? 'true' : 'false'"
			@click="toggleExpand"
			@keydown.enter.prevent="toggleExpand"
			@keydown.space.prevent="toggleExpand">
			<div class="reporting-dashboard__header-title-group">
				<ChartBar :size="20" class="reporting-dashboard__header-icon" />
				<span class="reporting-dashboard__header-title">Statistics & Analytics</span>

				<!-- LIVE EXECUTIVE KPI PILLS (always visible in header) -->
				<div v-if="!loading && !error" class="reporting-dashboard__header-pills">
					<span class="reporting-pill reporting-pill--neutral">
						{{ totalCount }} {{ totalCount === 1 ? 'Task' : 'Tasks' }}
					</span>
					<span class="reporting-pill reporting-pill--success">
						{{ completedCount }} Completed
					</span>
					<span v-if="overdueCount > 0" class="reporting-pill reporting-pill--danger">
						{{ overdueCount }} Overdue
					</span>
					<span class="reporting-pill reporting-pill--rate">
						{{ Math.round(completionRate) }}% Done
					</span>
				</div>
				<span v-else-if="loading" class="reporting-dashboard__header-loading">
					Loading statistics...
				</span>
			</div>

			<div class="reporting-dashboard__toggle-action">
				<NcButton type="tertiary"
					size="small"
					class="reporting-dashboard__toggle-btn"
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

		<!-- EXPANDED BODY (always visible if not inline, collapsible when inline) -->
		<div v-if="!inline || isExpanded" class="reporting-dashboard__body">
			<div v-if="loading" class="reporting-dashboard__loading">
				<div class="icon icon-loading" />
				<h3>Loading statistics...</h3>
			</div>
			<div v-else-if="error" class="reporting-dashboard__error">
				<h3>{{ error }}</h3>
			</div>
			<div v-else class="reporting-dashboard__content">
				<!-- KPI Grid -->
				<div class="reporting-dashboard__kpis">
					<div class="reporting-dashboard__kpi-card total-card">
						<div class="reporting-dashboard__kpi-icon total-icon">
							<ClipboardTextOutlineIcon :size="28" decorative />
						</div>
						<div class="reporting-dashboard__kpi-details">
							<span class="reporting-dashboard__kpi-value">{{ totalCount }}</span>
							<span class="reporting-dashboard__kpi-label">Total Tasks</span>
						</div>
					</div>
					<div class="reporting-dashboard__kpi-card completed-card">
						<div class="reporting-dashboard__kpi-icon completed-icon">
							<CheckCircleOutlineIcon :size="28" decorative />
						</div>
						<div class="reporting-dashboard__kpi-details">
							<span class="reporting-dashboard__kpi-value">{{ completedCount }}</span>
							<span class="reporting-dashboard__kpi-label">Completed</span>
						</div>
					</div>
					<div class="reporting-dashboard__kpi-card overdue-card" :class="{ 'has-overdue': overdueCount > 0 }">
						<div class="reporting-dashboard__kpi-icon overdue-icon">
							<ClockOutlineIcon :size="28" decorative />
						</div>
						<div class="reporting-dashboard__kpi-details">
							<span class="reporting-dashboard__kpi-value">{{ overdueCount }}</span>
							<span class="reporting-dashboard__kpi-label">Overdue</span>
						</div>
					</div>
					<div class="reporting-dashboard__kpi-card open-card">
						<div class="reporting-dashboard__kpi-icon open-icon">
							<FolderOpenOutlineIcon :size="28" decorative />
						</div>
						<div class="reporting-dashboard__kpi-details">
							<div class="reporting-dashboard__open-tasks-split">
								<div class="reporting-dashboard__split-important">
									<span class="reporting-dashboard__kpi-label important-label">Kritieke Processtap</span>
									<span class="reporting-dashboard__kpi-subvalue important-value">{{ openImportantCount }} / {{ totalImportantCount }}</span>
								</div>
								<div class="reporting-dashboard__split-divider" />
								<div>
									<span class="reporting-dashboard__kpi-label">Other Open Tasks</span>
									<span class="reporting-dashboard__kpi-subvalue">{{ openOtherCount }} / {{ totalOtherCount }}</span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Visualizations Section -->
				<div class="reporting-dashboard__charts">
					<!-- Completion Rate Chart -->
					<div class="reporting-dashboard__chart-card completion-card">
						<h3>Completion Rate</h3>
						<div class="reporting-dashboard__donut-container">
							<svg class="reporting-dashboard__donut" viewBox="0 0 120 120">
								<circle class="reporting-dashboard__donut-bg"
									cx="60"
									cy="60"
									r="50"
									fill="none"
									stroke-width="10" />
								<circle class="reporting-dashboard__donut-progress"
									cx="60"
									cy="60"
									r="50"
									fill="none"
									stroke-width="10"
									:stroke-dasharray="strokeDasharray"
									:stroke-dashoffset="strokeDashoffset"
									transform="rotate(-90 60 60)" />
							</svg>
							<div class="reporting-dashboard__donut-text">
								<span class="percentage-val">{{ Math.round(completionRate) }}%</span>
								<span class="percentage-label">done</span>
							</div>
						</div>
					</div>

					<!-- Task Distribution Chart -->
					<div class="reporting-dashboard__chart-card distribution-card">
						<h3>Task Distribution</h3>
						<div class="reporting-dashboard__bar-list">
							<div v-for="item in distributionData" :key="item.stackId" class="reporting-dashboard__bar-item">
								<div class="reporting-dashboard__bar-info">
									<span class="stack-title">{{ item.title }}</span>
									<span class="card-count">{{ item.count }} {{ item.count === 1 ? 'task' : 'tasks' }}</span>
								</div>
								<div class="reporting-dashboard__bar-track">
									<div class="reporting-dashboard__bar-progress"
										:style="{ width: item.percentage + '%', backgroundColor: item.color || 'var(--color-primary)' }" />
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import ClipboardTextOutlineIcon from 'vue-material-design-icons/ClipboardTextOutline.vue'
import CheckCircleOutlineIcon from 'vue-material-design-icons/CheckCircleOutline.vue'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import FolderOpenOutlineIcon from 'vue-material-design-icons/FolderOpenOutline.vue'
import ChartBar from 'vue-material-design-icons/ChartBar.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import { NcButton } from '@nextcloud/vue'

export default {
	name: 'ReportingDashboard',
	components: {
		ClipboardTextOutlineIcon,
		CheckCircleOutlineIcon,
		ClockOutlineIcon,
		FolderOpenOutlineIcon,
		ChartBar,
		ChevronDown,
		ChevronUp,
		NcButton,
	},
	props: {
		boardId: {
			type: Number,
			required: true,
		},
		inline: {
			type: Boolean,
			default: false,
		},
		preloaded: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			loading: true,
			error: null,
			isExpanded: true, // Default: OPEN as requested
		}
	},
	computed: {
		board() {
			return this.$store.state.currentBoard
		},
		stacksByBoard() {
			return this.board?.id ? this.$store.getters.stacksByBoard(this.board.id) : []
		},
		cards() {
			const list = []
			for (const s of this.stacksByBoard) {
				const cards = this.$store.getters.cardsByStack(s.id) || []
				for (const c of cards) {
					list.push({
						...c,
						stackTitle: s.title,
						stackDone: s.isDoneColumn,
					})
				}
			}
			return list
		},
		totalCount() {
			return this.cards.length
		},
		completedCount() {
			return this.cards.filter(c => this.isCardCompleted(c)).length
		},
		overdueCount() {
			const now = new Date()
			return this.cards.filter(c => {
				if (this.isCardCompleted(c)) return false
				if (!c.duedate) return false
				return new Date(c.duedate) < now
			}).length
		},
		// Important label cards (open / total)
		totalImportantCount() {
			return this.cards.filter(c => this.isImportant(c)).length
		},
		openImportantCount() {
			return this.cards.filter(c => this.isImportant(c) && !this.isCardCompleted(c)).length
		},
		// Non-important cards (open / total)
		totalOtherCount() {
			return this.cards.filter(c => !this.isImportant(c)).length
		},
		openOtherCount() {
			return this.cards.filter(c => !this.isImportant(c) && !this.isCardCompleted(c)).length
		},
		completionRate() {
			if (this.totalCount === 0) return 0
			return (this.completedCount / this.totalCount) * 100
		},
		strokeDasharray() {
			// Perimeter of circle r=50 is 2 * PI * r = 314.159
			return 2 * Math.PI * 50
		},
		strokeDashoffset() {
			const rate = Math.max(0, Math.min(100, this.completionRate))
			return this.strokeDasharray * (1 - rate / 100)
		},
		distributionData() {
			const maxCount = Math.max(...this.stacksByBoard.map(s => {
				const count = (this.$store.getters.cardsByStack(s.id) || []).length
				return count
			}), 1)

			// Colors matching board style or Nextcloud system colors
			const colors = [
				'var(--color-primary)',
				'#2ecc71',
				'#e67e22',
				'#9b59b6',
				'#34495e',
				'#1abc9c',
				'#f1c40f',
				'#e74c3c',
			]

			return this.stacksByBoard.map((s, index) => {
				const count = (this.$store.getters.cardsByStack(s.id) || []).length
				return {
					stackId: s.id,
					title: s.title || '',
					count,
					percentage: (count / maxCount) * 100,
					color: colors[index % colors.length],
				}
			})
		},
	},
	async created() {
		try {
			if (!this.preloaded) {
				await this.$store.dispatch('loadBoardById', this.boardId)
				await this.$store.dispatch('loadStacks', this.boardId)
			}
		} catch (e) {
			this.error = 'Failed to load board statistics'
		} finally {
			this.loading = false
		}
	},
	methods: {
		toggleExpand() {
			this.isExpanded = !this.isExpanded
		},
		isCardCompleted(card) {
			if (card.done) return true
			if (card.stackDone) return true
			const title = (card.stackTitle || '').toLowerCase()
			return title.includes('done')
				|| title.includes('afgerond')
				|| title.includes('completed')
				|| title.includes('afgehandeld')
		},
		isImportant(card) {
			if (!card.labels || !Array.isArray(card.labels)) return false
			return card.labels.some(l => l.title === 'Kritieke Processtap')
		},
	},
}
</script>

<style scoped>
.reporting-dashboard {
	padding: 24px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	border-radius: 12px;
	height: 100%;
	overflow-y: auto;
	box-sizing: border-box;
}

.reporting-dashboard--inline {
	margin: 16px 20px 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 12px);
	background: var(--color-main-background);
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
	overflow: hidden;
	padding: 0;
	height: auto;
	flex: 0 0 auto !important;
	flex-shrink: 0 !important;
	box-sizing: border-box;
	transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.reporting-dashboard--inline:hover {
	border-color: var(--color-border-dark, var(--color-border));
}

/* HEADER STRIP */
.reporting-dashboard__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 10px 16px;
	min-height: 44px;
	box-sizing: border-box;
	background: var(--color-background-hover);
	cursor: pointer;
	user-select: none;
	gap: 12px;
	border-bottom: 1px solid transparent;
	transition: background 0.15s ease, border-color 0.15s ease;
	flex-shrink: 0;
}

.reporting-dashboard--expanded .reporting-dashboard__header {
	border-bottom-color: var(--color-border);
}

.reporting-dashboard__header:hover {
	background: var(--color-background-dark, rgba(0, 0, 0, 0.05));
}

.reporting-dashboard__header-title-group {
	display: flex;
	align-items: center;
	gap: 12px;
	flex-wrap: wrap;
	min-width: 0;
}

.reporting-dashboard__header-icon {
	color: var(--color-primary-element);
	flex-shrink: 0;
}

.reporting-dashboard__header-title {
	font-weight: 700;
	font-size: 14px;
	color: var(--color-main-text);
	white-space: nowrap;
}

.reporting-dashboard__header-pills {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	flex-wrap: wrap;
}

.reporting-pill {
	display: inline-flex;
	align-items: center;
	padding: 2px 9px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 600;
	white-space: nowrap;
	line-height: 1.3;
}

.reporting-pill--neutral {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	color: var(--color-main-text);
}

.reporting-pill--success {
	background: var(--color-success-light, rgba(70, 186, 97, 0.15));
	color: var(--color-success, #27ae60);
	border: 1px solid var(--color-success, #27ae60);
}

.reporting-pill--danger {
	background: var(--color-error-light, rgba(231, 76, 60, 0.15));
	color: var(--color-error, #c0392b);
	border: 1px solid var(--color-error, #c0392b);
}

.reporting-pill--rate {
	background: var(--color-primary-element-light, rgba(0, 130, 201, 0.15));
	color: var(--color-primary-element);
	border: 1px solid var(--color-primary-element);
}

.reporting-dashboard__header-loading {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.reporting-dashboard__toggle-action {
	display: flex;
	align-items: center;
	flex-shrink: 0;
}

/* BODY WHEN INLINE */
.reporting-dashboard--inline .reporting-dashboard__body {
	padding: 14px 18px 18px;
	background: var(--color-main-background);
}

.reporting-dashboard--inline .reporting-dashboard__kpis {
	margin-bottom: 12px;
	gap: 12px;
}

.reporting-dashboard--inline .reporting-dashboard__kpi-card {
	padding: 10px 14px;
	gap: 10px;
	border-radius: var(--border-radius-large, 10px);
}

.reporting-dashboard--inline .reporting-dashboard__kpi-value {
	font-size: 22px;
}

.reporting-dashboard--inline .reporting-dashboard__kpi-label {
	font-size: 12px;
}

.reporting-dashboard--inline .reporting-dashboard__charts {
	gap: 12px;
}

.reporting-dashboard--inline .reporting-dashboard__chart-card {
	padding: 12px 16px;
	border-radius: var(--border-radius-large, 10px);
}

.reporting-dashboard--inline .reporting-dashboard__chart-card h3 {
	margin: 0 0 8px 0;
	font-size: 13px;
}

.reporting-dashboard--inline .reporting-dashboard__donut-container {
	width: 120px;
	height: 120px;
}

.reporting-dashboard--inline .reporting-dashboard__donut-text .percentage-val {
	font-size: 22px;
}

.reporting-dashboard--inline .reporting-dashboard__bar-list {
	gap: 8px;
}

@media (max-width: 900px) {
	.reporting-dashboard--inline {
		margin: 12px 14px 0;
	}
}

@media (max-width: 600px) {
	.reporting-dashboard--inline {
		margin: 8px 10px 0;
	}
}

.reporting-dashboard__loading,
.reporting-dashboard__error {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	min-height: 200px;
	color: var(--color-text-maxcontrast);
}

.reporting-dashboard__kpis {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 16px;
	margin-bottom: 24px;
}

.reporting-dashboard__kpi-card {
	display: flex;
	align-items: center;
	gap: 16px;
	padding: 20px;
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	box-shadow: 0 2px 8px rgba(0,0,0,0.02);
	transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.reporting-dashboard__kpi-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}

.reporting-dashboard__kpi-card.total-card {
	border-color: rgba(52, 152, 219, 0.3);
	background: rgba(52, 152, 219, 0.04);
}

.reporting-dashboard__kpi-card.total-card .reporting-dashboard__kpi-icon {
	color: #3498db;
	background: rgba(52, 152, 219, 0.1);
	border-color: rgba(52, 152, 219, 0.2);
}

.reporting-dashboard__kpi-card.completed-card {
	border-color: rgba(46, 204, 113, 0.3);
	background: rgba(46, 204, 113, 0.04);
}

.reporting-dashboard__kpi-card.completed-card .reporting-dashboard__kpi-icon {
	color: #2ecc71;
	background: rgba(46, 204, 113, 0.1);
	border-color: rgba(46, 204, 113, 0.2);
}

.reporting-dashboard__kpi-card.overdue-card {
	border-color: rgba(149, 165, 166, 0.3);
	background: rgba(149, 165, 166, 0.04);
}

.reporting-dashboard__kpi-card.overdue-card .reporting-dashboard__kpi-icon {
	color: #7f8c8d;
	background: rgba(149, 165, 166, 0.1);
	border-color: rgba(149, 165, 166, 0.2);
}

.reporting-dashboard__kpi-card.overdue-card.has-overdue {
	border-color: rgba(231, 76, 60, 0.3);
	background: rgba(231, 76, 60, 0.06);
}

.reporting-dashboard__kpi-card.overdue-card.has-overdue .reporting-dashboard__kpi-icon {
	color: #e74c3c;
	background: rgba(231, 76, 60, 0.15);
	border-color: rgba(231, 76, 60, 0.3);
}

.reporting-dashboard__kpi-card.open-card {
	border-color: rgba(243, 156, 18, 0.3);
	background: rgba(243, 156, 18, 0.04);
}

.reporting-dashboard__kpi-card.open-card .reporting-dashboard__kpi-icon {
	color: #f39c12;
	background: rgba(243, 156, 18, 0.1);
	border-color: rgba(243, 156, 18, 0.2);
}

.reporting-dashboard__kpi-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 52px;
	height: 52px;
	border-radius: 12px;
	border: 1px solid transparent;
	flex-shrink: 0;
}

.reporting-dashboard__kpi-details {
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.reporting-dashboard__kpi-value {
	font-size: 24px;
	font-weight: 700;
	line-height: 1.1;
	color: var(--color-main-text);
}

.reporting-dashboard__kpi-label {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	margin-top: 4px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.reporting-dashboard__open-tasks-split {
	display: flex;
	align-items: center;
	gap: 12px;
}

.reporting-dashboard__split-divider {
	width: 1px;
	height: 32px;
	background-color: var(--color-border);
}

.reporting-dashboard__split-important .important-label {
	color: var(--color-primary);
	font-weight: 600;
}

.reporting-dashboard__kpi-subvalue {
	font-size: 16px;
	font-weight: 700;
	color: var(--color-main-text);
	display: block;
}

.reporting-dashboard__split-important .important-value {
	color: var(--color-primary);
}

.reporting-dashboard__charts {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 16px;
}

.reporting-dashboard__chart-card {
	padding: 20px;
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	display: flex;
	flex-direction: column;
}

.reporting-dashboard__chart-card h3 {
	margin: 0 0 16px 0;
	font-size: 15px;
	font-weight: 600;
	color: var(--color-main-text);
}

/* Donut Chart */
.reporting-dashboard__donut-container {
	position: relative;
	width: 150px;
	height: 150px;
	margin: auto;
}

.reporting-dashboard__donut {
	width: 100%;
	height: 100%;
}

.reporting-dashboard__donut-bg {
	stroke: var(--color-border);
}

.reporting-dashboard__donut-progress {
	stroke: var(--color-primary);
	stroke-linecap: round;
	transition: stroke-dashoffset 0.8s ease-in-out;
}

.reporting-dashboard__donut-text {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	pointer-events: none;
}

.reporting-dashboard__donut-text .percentage-val {
	font-size: 26px;
	font-weight: 700;
	color: var(--color-main-text);
	line-height: 1;
}

.reporting-dashboard__donut-text .percentage-label {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	margin-top: 2px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

/* Bar Distribution Chart */
.reporting-dashboard__bar-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
	flex-grow: 1;
	justify-content: center;
}

.reporting-dashboard__bar-item {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.reporting-dashboard__bar-info {
	display: flex;
	justify-content: space-between;
	font-size: 13px;
}

.reporting-dashboard__bar-info .stack-title {
	font-weight: 500;
	color: var(--color-main-text);
}

.reporting-dashboard__bar-info .card-count {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.reporting-dashboard__bar-track {
	height: 8px;
	background-color: var(--color-border);
	border-radius: 4px;
	overflow: hidden;
}

.reporting-dashboard__bar-progress {
	height: 100%;
	border-radius: 4px;
	transition: width 0.5s ease-out;
}
</style>
