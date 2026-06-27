<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="reporting-dashboard">
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
				<div class="reporting-dashboard__kpi-card">
					<div class="reporting-dashboard__kpi-icon total-icon">
						<ClipboardTextOutlineIcon :size="28" decorative />
					</div>
					<div class="reporting-dashboard__kpi-details">
						<span class="reporting-dashboard__kpi-value">{{ totalCount }}</span>
						<span class="reporting-dashboard__kpi-label">Total Tasks</span>
					</div>
				</div>
				<div class="reporting-dashboard__kpi-card">
					<div class="reporting-dashboard__kpi-icon completed-icon">
						<CheckCircleOutlineIcon :size="28" decorative />
					</div>
					<div class="reporting-dashboard__kpi-details">
						<span class="reporting-dashboard__kpi-value">{{ completedCount }}</span>
						<span class="reporting-dashboard__kpi-label">Completed</span>
					</div>
				</div>
				<div class="reporting-dashboard__kpi-card" :class="{ 'has-overdue': overdueCount > 0 }">
					<div class="reporting-dashboard__kpi-icon overdue-icon">
						<ClockOutlineIcon :size="28" decorative />
					</div>
					<div class="reporting-dashboard__kpi-details">
						<span class="reporting-dashboard__kpi-value">{{ overdueCount }}</span>
						<span class="reporting-dashboard__kpi-label">Overdue</span>
					</div>
				</div>
				<div class="reporting-dashboard__kpi-card">
					<div class="reporting-dashboard__kpi-icon open-icon">
						<FolderOpenOutlineIcon :size="28" decorative />
					</div>
					<div class="reporting-dashboard__kpi-details">
						<div class="reporting-dashboard__open-tasks-split">
							<div>
								<span class="reporting-dashboard__kpi-label">Kritieke Processtap</span>
								<span class="reporting-dashboard__kpi-subvalue">{{ openImportantCount }} / {{ totalImportantCount }}</span>
							</div>
							<div class="reporting-dashboard__split-divider"></div>
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
							<circle class="reporting-dashboard__donut-bg" cx="60" cy="60" r="50" fill="none" stroke-width="10" />
							<circle class="reporting-dashboard__donut-progress" cx="60" cy="60" r="50" fill="none" stroke-width="10"
								:stroke-dasharray="strokeDasharray" :stroke-dashoffset="strokeDashoffset" transform="rotate(-90 60 60)" />
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
</template>

<script>
import ClipboardTextOutlineIcon from 'vue-material-design-icons/ClipboardTextOutline.vue'
import CheckCircleOutlineIcon from 'vue-material-design-icons/CheckCircleOutline.vue'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import FolderOpenOutlineIcon from 'vue-material-design-icons/FolderOpenOutline.vue'

export default {
	name: 'ReportingDashboard',
	components: {
		ClipboardTextOutlineIcon,
		CheckCircleOutlineIcon,
		ClockOutlineIcon,
		FolderOpenOutlineIcon,
	},
	props: {
		boardId: {
			type: Number,
			required: true,
		},
	},
	data() {
		return {
			loading: true,
			error: null,
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
						stackDone: s.done,
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
				'#e74c3c'
			]

			return this.stacksByBoard.map((s, index) => {
				const count = (this.$store.getters.cardsByStack(s.id) || []).length
				return {
					stackId: s.id,
					title: s.title || '',
					count,
					percentage: (count / maxCount) * 100,
					color: colors[index % colors.length]
				}
			})
		}
	},
	async created() {
		try {
			await this.$store.dispatch('loadBoardById', this.boardId)
			await this.$store.dispatch('loadStacks', this.boardId)
		} catch (e) {
			this.error = 'Failed to load board statistics'
		} finally {
			this.loading = false
		}
	},
	methods: {
		isCardCompleted(card) {
			if (card.done) return true
			if (card.stackDone) return true
			const title = (card.stackTitle || '').toLowerCase()
			return title.includes('done') || 
				title.includes('afgerond') || 
				title.includes('completed') || 
				title.includes('afgehandeld')
		},
		isImportant(card) {
			if (!card.labels || !Array.isArray(card.labels)) return false
			return card.labels.some(l => l.title === 'Kritieke Processtap')
		}
	}
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

.reporting-dashboard__loading,
.reporting-dashboard__error {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	min-height: 300px;
	color: var(--color-text-maxcontrast);
}

.reporting-dashboard__kpis {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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

.reporting-dashboard__kpi-card.has-overdue {
	border-color: #e74c3c;
	background: rgba(231, 76, 60, 0.05);
}

.reporting-dashboard__kpi-icon {
	font-size: 32px;
	width: 54px;
	height: 54px;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
}

.reporting-dashboard__kpi-details {
	display: flex;
	flex-direction: column;
	flex: 1;
}

.reporting-dashboard__kpi-value {
	font-size: 28px;
	font-weight: 700;
	line-height: 1.2;
}

.reporting-dashboard__kpi-label {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.reporting-dashboard__open-tasks-split {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.reporting-dashboard__open-tasks-split > div {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.reporting-dashboard__open-tasks-split .reporting-dashboard__kpi-label {
	font-size: 12px;
}

.reporting-dashboard__kpi-subvalue {
	font-size: 15px;
	font-weight: 600;
	color: var(--color-main-text);
}

.reporting-dashboard__split-divider {
	height: 1px;
	background: var(--color-border);
}

.reporting-dashboard__charts {
	display: grid;
	grid-template-columns: 1fr 2fr;
	gap: 20px;
}

@media (max-width: 900px) {
	.reporting-dashboard__charts {
		grid-template-columns: 1fr;
	}
}

.reporting-dashboard__chart-card {
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 20px;
}

.reporting-dashboard__chart-card h3 {
	margin-top: 0;
	margin-bottom: 20px;
	font-size: 16px;
	font-weight: 600;
	border-bottom: 1px solid var(--color-border);
	padding-bottom: 8px;
}

.reporting-dashboard__donut-container {
	position: relative;
	width: 160px;
	height: 160px;
	margin: 0 auto;
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
	transition: stroke-dashoffset 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.reporting-dashboard__donut-text {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	display: flex;
	flex-direction: column;
	align-items: center;
}

.percentage-val {
	font-size: 24px;
	font-weight: 700;
}

.percentage-label {
	font-size: 11px;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
	letter-spacing: 0.5px;
}

.reporting-dashboard__bar-list {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.reporting-dashboard__bar-item {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.reporting-dashboard__bar-info {
	display: flex;
	justify-content: space-between;
	font-size: 13px;
}

.stack-title {
	font-weight: 600;
}

.card-count {
	color: var(--color-text-maxcontrast);
}

.reporting-dashboard__bar-track {
	height: 12px;
	background: var(--color-border);
	border-radius: 6px;
	overflow: hidden;
}

.reporting-dashboard__bar-progress {
	height: 100%;
	border-radius: 6px;
	transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
