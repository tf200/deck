/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const groupCardsByProject = (cards = [], projectsByBoard = {}) => {
	const projects = new Map()
	const otherCards = []

	for (const card of cards) {
		const project = projectsByBoard[String(card.boardId)]
		if (!project) {
			otherCards.push(card)
			continue
		}

		const boardId = String(card.boardId)
		if (!projects.has(boardId)) {
			projects.set(boardId, {
				boardId,
				name: project.number ? `${project.number} - ${project.name}` : project.name,
				cards: [],
			})
		}
		projects.get(boardId).cards.push(card)
	}

	return [...projects.values(), { boardId: null, name: null, cards: otherCards }]
		.filter(group => group.cards.length > 0)
}

export const projectGroupsToItems = (groups) => {
	return groups.flatMap(group => {
		if (group.boardId === null) {
			return group.cards
		}

		return [{
			id: `project-${group.boardId}`,
			isProjectGroup: true,
			name: group.name,
		}, ...group.cards]
	})
}
