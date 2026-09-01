/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { BoardApi } from '../services/BoardApi.js'
import storeFactory from './main.js'

jest.mock('@nextcloud/initial-state', () => ({
	loadState: jest.fn((app, key, fallback) => fallback),
}))

jest.mock('@nextcloud/axios', () => ({}))

jest.mock('@nextcloud/dialogs', () => ({
	showError: jest.fn(),
}))

jest.mock('@nextcloud/router', () => ({
	generateOcsUrl: jest.fn(),
	generateUrl: jest.fn(),
}))

jest.mock('../services/BoardApi.js', () => {
	return {
		BoardApi: jest.fn().mockImplementation(() => ({ loadById: jest.fn() })),
	}
})

describe('Deck store', () => {
	const boardApi = BoardApi.mock.results[0].value

	beforeEach(() => {
		loadState.mockImplementation((app, key, fallback) => fallback)
		boardApi.loadById.mockReset()
		global.localStorage = {
			getItem: jest.fn(() => null),
			setItem: jest.fn(),
		}
	})

	it('supports loading a board without Deck page initial state', async () => {
		const board = { id: 35, users: [] }
		boardApi.loadById.mockResolvedValue(board)
		const store = storeFactory()

		expect(store.state.boards).toEqual([])
		expect(store.getters.boardById(35)).toBeUndefined()

		await store.dispatch('loadBoardById', 35)

		expect(store.getters.boardById(35)).toBe(board)
		expect(store.state.currentBoard).toBe(board)
	})
})
