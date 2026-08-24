<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

final class CommentType {
	public const METADATA_KEY = 'deck.noteType';
	public const DEFAULT = 'general';

	public const TYPES = [
		'general',
		'customer',
		'internal',
		'decision',
		'risk_blocker',
		'action_point',
		'technical',
		'audit',
	];

	public const LABELS = [
		'general' => 'General note',
		'customer' => 'Customer note',
		'internal' => 'Internal note',
		'decision' => 'Decision',
		'risk_blocker' => 'Risk / blocker',
		'action_point' => 'Action point',
		'technical' => 'Technical note',
		'audit' => 'Audit note',
	];

	public static function isValid(mixed $type): bool {
		return is_string($type) && in_array($type, self::TYPES, true);
	}

	public static function normalize(mixed $type): string {
		return self::isValid($type) ? $type : self::DEFAULT;
	}
}
