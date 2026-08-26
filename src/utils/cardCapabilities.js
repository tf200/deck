/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const isSameId = (first, second) => first !== null
	&& first !== undefined
	&& second !== null
	&& second !== undefined
	&& Number(first) === Number(second)

export const canVerifyCard = card => card?.canVerify !== false

export const boardUsesStackCompletion = board => board?.completionByStack === true

export const isCombiProjectBoard = board => board?.isProjectBoard === true
	&& Number(board?.projectType) === 0

export const hasCardMoveCapability = card => card?.canMove !== false
	|| card?.canSign !== false
	|| card?.canVerify !== false

/**
 * Check the action required to move a card to a target list.
 *
 * @param {object} card Card response
 * @param {object} board Source board response
 * @param {number} targetStackId Target list ID
 * @return {boolean} Whether the target-specific action is allowed
 */
export function canMoveCardToStack(card, board, targetStackId) {
	let capability = 'canMove'
	if (!isSameId(card?.stackId, targetStackId)) {
		if (isSameId(board?.doneStackId, targetStackId)) {
			capability = 'canVerify'
		} else if (isSameId(board?.approvedStackId, targetStackId)) {
			capability = 'canSign'
		}
	}

	// Capability fields are absent on older and federated Deck servers.
	return card?.[capability] !== false
}
