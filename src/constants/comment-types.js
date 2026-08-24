/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const DEFAULT_COMMENT_TYPE = 'general'

export const COMMENT_TYPES = [
	{ value: 'general', label: 'General note' },
	{ value: 'customer', label: 'Customer note' },
	{ value: 'internal', label: 'Internal note' },
	{ value: 'decision', label: 'Decision' },
	{ value: 'risk_blocker', label: 'Risk / blocker' },
	{ value: 'action_point', label: 'Action point' },
	{ value: 'technical', label: 'Technical note' },
	{ value: 'audit', label: 'Audit note' },
]

/**
 * Return the display label for a comment type.
 *
 * @param {string} value
 * @return {string}
 */
export function commentTypeLabel(value) {
	switch (value) {
	case 'customer':
		return t('deck', 'Customer note')
	case 'internal':
		return t('deck', 'Internal note')
	case 'decision':
		return t('deck', 'Decision')
	case 'risk_blocker':
		return t('deck', 'Risk / blocker')
	case 'action_point':
		return t('deck', 'Action point')
	case 'technical':
		return t('deck', 'Technical note')
	case 'audit':
		return t('deck', 'Audit note')
	default:
		return t('deck', 'General note')
	}
}
