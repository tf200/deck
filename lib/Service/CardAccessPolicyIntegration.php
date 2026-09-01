<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Card;
use OCA\Deck\Db\IPermissionMapper;
use Psr\Log\LoggerInterface;

class CardAccessPolicyIntegration {
	private const PROVIDER_CLASS = 'OCA\ProjectCreatorAIO\Service\CardPolicyService';

	private bool $resolved = false;
	private ?object $provider = null;

	public function __construct(
		private LoggerInterface $logger,
	) {
	}

	public function checkPermission(IPermissionMapper $mapper, mixed $id, int $permission, ?string $userId): bool {
		return $this->getProvider()?->checkPermission($mapper, $id, $permission, $userId) ?? true;
	}

	public function hasFullBoardAccess(int $boardId, ?string $userId): bool {
		$provider = $this->getProvider();
		if ($provider === null || !method_exists($provider, 'hasFullBoardAccess')) {
			return false;
		}

		try {
			return $provider->hasFullBoardAccess($boardId, $userId);
		} catch (\Throwable $e) {
			$this->logger->debug('Project board access is unavailable', ['exception' => $e]);
			return false;
		}
	}

	/**
	 * @param Card[] $cards
	 * @return Card[]
	 */
	public function filterVisibleCards(array $cards, ?string $userId): array {
		return $this->getProvider()?->filterVisibleCards($cards, $userId) ?? $cards;
	}

	public function assertTransition(Card $card, int $targetStackId, ?string $userId): void {
		$this->getProvider()?->assertTransition($card, $targetStackId, $userId);
	}

	public function assertAction(Card $card, string $action, ?string $userId): void {
		$this->getProvider()?->assertAction($card, $action, $userId);
	}

	public function usesStackCompletion(Card $card): bool {
		return $this->getProvider()?->usesStackCompletion($card) ?? false;
	}

	public function isCombiProjectCard(Card $card): bool {
		$provider = $this->getProvider();
		if ($provider === null || !method_exists($provider, 'isCombiProjectCard')) {
			return false;
		}

		try {
			return $provider->isCombiProjectCard($card);
		} catch (\Throwable $e) {
			$this->logger->debug('Combi project card status is unavailable', ['exception' => $e]);
			return false;
		}
	}

	/**
	 * @return array{canMove: bool, canSign: bool, canVerify: bool}
	 */
	public function getCapabilities(Card $card, ?string $userId): array {
		$defaults = [
			'canMove' => true,
			'canSign' => true,
			'canVerify' => true,
		];
		$provider = $this->getProvider();
		if ($provider === null || !method_exists($provider, 'getCapabilities')) {
			return $defaults;
		}

		try {
			return $provider->getCapabilities($card, $userId);
		} catch (\Throwable $e) {
			$this->logger->debug('Project card capabilities are unavailable', ['exception' => $e]);
			return $defaults;
		}
	}

	private function getProvider(): ?object {
		if ($this->resolved) {
			return $this->provider;
		}

		$this->resolved = true;
		if (!class_exists(self::PROVIDER_CLASS)) {
			return null;
		}

		try {
			$this->provider = \OCP\Server::get(self::PROVIDER_CLASS);
		} catch (\Throwable $e) {
			$this->logger->debug('Project card policy integration is unavailable', ['exception' => $e]);
		}

		return $this->provider;
	}
}
