<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;

class PrivateNote extends Entity implements \JsonSerializable {
	protected $cardId;
	protected $userId;
	protected $text;
	protected $createdAt;
	protected $lastModified;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('cardId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('lastModified', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'cardId' => $this->cardId,
			'userId' => $this->userId,
			'text' => $this->text,
			'createdAt' => $this->createdAt,
			'lastModified' => $this->lastModified,
		];
	}
}
