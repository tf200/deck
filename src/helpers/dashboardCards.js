/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const getUpcomingCards = (cardsByBucket, days, now = new Date()) => {
	const start = new Date(now)
	start.setHours(0, 0, 0, 0)

	const end = new Date(start)
	end.setDate(end.getDate() + days)

	return Object.values(cardsByBucket || {}).flat()
		.filter(card => {
			if (card.duedate === null) {
				return false
			}

			const due = new Date(card.duedate)
			return due >= start && due < end
		})
		.sort((a, b) => new Date(a.duedate) - new Date(b.duedate))
}
