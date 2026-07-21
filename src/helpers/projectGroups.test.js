/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { groupCardsByProject, projectGroupsToItems } from './projectGroups.js'

describe('projectGroups', () => {
	it('groups mapped boards under their project and keeps other cards unchanged', () => {
		const cards = [
			{ id: 1, boardId: 10 },
			{ id: 2, boardId: 20 },
			{ id: 3, boardId: 10 },
		]
		const projectsByBoard = {
			10: { boardId: 10, number: 'PRJ-10', name: 'Website' },
		}

		const groups = groupCardsByProject(cards, projectsByBoard)

		expect(groups).toEqual([
			{
				boardId: '10',
				name: 'PRJ-10 - Website',
				cards: [cards[0], cards[2]],
			},
			{
				boardId: null,
				name: null,
				cards: [cards[1]],
			},
		])
		expect(projectGroupsToItems(groups)).toEqual([
			{ id: 'project-10', isProjectGroup: true, name: 'PRJ-10 - Website' },
			cards[0],
			cards[2],
			cards[1],
		])
	})
})
