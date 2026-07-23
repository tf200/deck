/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getUpcomingCards } from './dashboardCards.js'

describe('getUpcomingCards', () => {
	const now = new Date('2026-07-23T15:00:00')
	const card = (id, duedate) => ({ id, duedate })

	it('includes today and excludes overdue, no-due, and the end boundary', () => {
		const cards = {
			overdue: [card(1, '2026-07-22T23:59:59')],
			today: [card(2, '2026-07-23T08:00:00')],
			later: [
				card(3, '2026-07-29T23:59:59'),
				card(4, '2026-07-30T00:00:00'),
			],
			nodue: [card(5, null)],
		}

		expect(getUpcomingCards(cards, 7, now).map(({ id }) => id)).toEqual([2, 3])
	})

	it('sorts cards and supports a 90-day range', () => {
		const cards = {
			later: [
				card(1, '2026-10-20T12:00:00'),
				card(2, '2026-08-01T12:00:00'),
				card(3, '2026-10-21T12:00:00'),
			],
		}

		expect(getUpcomingCards(cards, 90, now).map(({ id }) => id)).toEqual([2, 1])
	})
})
