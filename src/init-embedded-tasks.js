/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import { createPinia, PiniaVuePlugin } from 'pinia'
import storeFactory from './store/main.js'
import ClickOutside from 'vue-click-outside'
import { translate, translatePlural } from '@nextcloud/l10n'
import Board from './components/board/Board.vue'
import ReportingDashboard from './components/board/ReportingDashboard.vue'
import { BoardApi } from './services/BoardApi.js'
import './shared-init.js'
import './models/index.js'

// Standard styles
import './css/dashboard.scss'

const boardApi = new BoardApi()

Vue.use(PiniaVuePlugin)
Vue.directive('click-outside', ClickOutside)
Vue.prototype.t = translate
Vue.prototype.n = translatePlural

Vue.directive('focus', {
	inserted(el) {
		el.focus()
	},
})

// OCA Namespace configuration
window.OCA = window.OCA || {}
window.OCA.Deck = window.OCA.Deck || {}

const EmbeddedBoardWrapper = {
	name: 'EmbeddedBoardWrapper',
	props: ['initialBoardId'],
	data() {
		return {
			boardId: this.initialBoardId,
		}
	},
	render(h) {
		return h(Board, {
			props: {
				id: Number(this.boardId),
			}
		})
	}
}

window.OCA.Deck.EmbeddedTasks = {
	mount({ el, boardId }) {
		const pinia = createPinia()
		const store = storeFactory()

		// Set full app to false so that the sidebar/navigation acts properly in embedded contexts
		store.commit('setFullApp', false)

		// Create wrapper container to avoid replacing el (which keeps classes like .deck-board__mount)
		const mountEl = document.createElement('div')
		mountEl.style.height = '100%'
		mountEl.style.width = '100%'
		el.appendChild(mountEl)

		const vm = new Vue({
			el: mountEl,
			store,
			pinia,
			provide() {
				return {
					boardApi,
				}
			},
			render: h => h(EmbeddedBoardWrapper, {
				ref: 'wrapper',
				props: {
					initialBoardId: Number(boardId),
				}
			})
		})

		return {
			destroy() {
				vm.$destroy()
				el.innerHTML = ''
			},
			setBoardId(newBoardId) {
				if (vm.$refs.wrapper) {
					vm.$refs.wrapper.boardId = Number(newBoardId)
				}
			}
		}
	}
}

window.OCA.Deck.EmbeddedAnalytics = {
	mount({ el, boardId }) {
		const pinia = createPinia()
		const store = storeFactory()

		store.commit('setFullApp', false)

		const mountEl = document.createElement('div')
		mountEl.style.height = '100%'
		mountEl.style.width = '100%'
		el.appendChild(mountEl)

		const vm = new Vue({
			el: mountEl,
			store,
			pinia,
			provide() {
				return {
					boardApi,
				}
			},
			render: h => h(ReportingDashboard, {
				props: {
					boardId: Number(boardId),
				}
			})
		})

		return {
			destroy() {
				vm.$destroy()
				el.innerHTML = ''
			},
			setBoardId(newBoardId) {
				// handled by remount in DeckAnalytics.vue
			}
		}
	}
}
