/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	boardUsesStackCompletion,
	canMoveCardToStack,
	canVerifyCard,
	hasCardMoveCapability,
} from '../../src/utils/cardCapabilities.js'

describe('card capabilities', () => {
	const board = { approvedStackId: 20, doneStackId: 30 }

	test('allows older card responses without capability fields', () => {
		const card = { stackId: 10 }

		expect(canMoveCardToStack(card, board, 11)).toBe(true)
		expect(canMoveCardToStack(card, board, 20)).toBe(true)
		expect(canMoveCardToStack(card, board, 30)).toBe(true)
		expect(canVerifyCard(card)).toBe(true)
		expect(hasCardMoveCapability(card)).toBe(true)
	})

	test('uses the capability for the selected target', () => {
		const card = { stackId: 10, canMove: false, canSign: true, canVerify: false }

		expect(canMoveCardToStack(card, board, 11)).toBe(false)
		expect(canMoveCardToStack(card, board, 20)).toBe(true)
		expect(canMoveCardToStack(card, board, 30)).toBe(false)
		expect(canVerifyCard(card)).toBe(false)
		expect(hasCardMoveCapability(card)).toBe(true)
	})

	test('uses move permission when reordering in the current list', () => {
		const card = { stackId: 20, canMove: false, canSign: true, canVerify: true }

		expect(canMoveCardToStack(card, board, 20)).toBe(false)
	})

	test('identifies boards where completion is controlled by the done stack', () => {
		expect(boardUsesStackCompletion({ completionByStack: true })).toBe(true)
		expect(boardUsesStackCompletion({ completionByStack: false })).toBe(false)
		expect(boardUsesStackCompletion()).toBe(false)
	})
})
