/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import Vuex from 'vuex'
import { OverviewApi } from '../services/OverviewApi.js'
import { ProjectApi } from '../services/ProjectApi.js'

Vue.use(Vuex)

const apiClient = new OverviewApi()
const projectApi = new ProjectApi()

export default {
	state: {
		assignedCards: [],
		projectsByBoard: {},
	},
	getters: {
		assignedCardsDashboard: state => {
			return state.assignedCards
		},
		projectsByBoard: state => state.projectsByBoard,
	},
	mutations: {
		setAssignedCards(state, assignedCards) {
			state.assignedCards = assignedCards
		},
		setProjectsByBoard(state, projects) {
			state.projectsByBoard = Object.fromEntries(projects.map(project => [String(project.boardId), project]))
		},
	},
	actions: {
		async loadUpcoming({ commit }) {
			const [upcommingCards, projects] = await Promise.all([
				apiClient.get('upcoming'),
				projectApi.getBoardMappings(),
			])
			commit('setAssignedCards', upcommingCards)
			commit('setProjectsByBoard', projects)
		},
	},
}
