/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { PrivateNoteApi } from '../services/PrivateNoteApi.js'
import Vue from 'vue'

const apiClient = new PrivateNoteApi()

export default {
	state: {
		notes: {},
	},
	getters: {
		getNotesForCard: (state) => (cardId) => {
			return state.notes[cardId] || []
		},
	},
	mutations: {
		setNotes(state, { cardId, notes }) {
			Vue.set(state.notes, cardId, notes)
		},
		addNote(state, { cardId, note }) {
			if (!state.notes[cardId]) {
				Vue.set(state.notes, cardId, [])
			}
			state.notes[cardId].unshift(note)
		},
		updateNote(state, { cardId, note }) {
			if (state.notes[cardId]) {
				const existingIndex = state.notes[cardId].findIndex(n => n.id === note.id)
				if (existingIndex !== -1) {
					Vue.set(state.notes[cardId], existingIndex, note)
				}
			}
		},
		deleteNote(state, { cardId, noteId }) {
			if (state.notes[cardId]) {
				const existingIndex = state.notes[cardId].findIndex(n => n.id === noteId)
				if (existingIndex !== -1) {
					state.notes[cardId].splice(existingIndex, 1)
				}
			}
		},
	},
	actions: {
		async fetchNotes({ commit }, cardId) {
			const notes = await apiClient.loadNotes(cardId)
			commit('setNotes', { cardId, notes })
		},
		async createNote({ commit }, { cardId, text }) {
			const note = await apiClient.createNote({ cardId, text })
			commit('addNote', { cardId, note })
		},
		async updateNote({ commit }, { cardId, noteId, text }) {
			const note = await apiClient.updateNote({ cardId, noteId, text })
			commit('updateNote', { cardId, note })
		},
		async deleteNote({ commit }, { cardId, noteId }) {
			await apiClient.deleteNote({ cardId, noteId })
			commit('deleteNote', { cardId, noteId })
		},
	},
}
