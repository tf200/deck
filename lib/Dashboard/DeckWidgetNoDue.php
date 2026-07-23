<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Dashboard;

use OCP\Dashboard\IWidget;
use OCP\IL10N;
use OCP\Util;

class DeckWidgetNoDue implements IWidget {
	public function __construct(private IL10N $l10n) {
	}

	public function getId(): string {
		return 'deckNoDue';
	}

	public function getTitle(): string {
		return $this->l10n->t('Cards without a due date');
	}

	public function getOrder(): int {
		return 22;
	}

	public function getIconClass(): string {
		return 'icon-deck';
	}

	public function getUrl(): ?string {
		return null;
	}

	public function load(): void {
		Util::addScript('deck', 'deck-dashboard');
	}
}
