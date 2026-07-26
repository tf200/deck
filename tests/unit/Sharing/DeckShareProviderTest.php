<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Sharing;

use OCA\Deck\Cache\AttachmentCacheHelper;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IMimeTypeLoader;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Test\TestCase;

class DeckShareProviderTest extends TestCase {
	public function testRecipientCannotResolveAttachmentShareForHiddenCard(): void {
		$cardMapper = $this->createMock(CardMapper::class);
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->expects($this->once())
			->method('checkPermission')
			->with($cardMapper, 42, Acl::PERMISSION_READ, 'alice', true)
			->willThrowException(new NoPermissionException('Hidden by card policy'));
		$provider = new DeckShareProvider(
			$this->createMock(IDBConnection::class),
			$this->createMock(IManager::class),
			$this->createMock(BoardMapper::class),
			$cardMapper,
			$permissionService,
			$this->createMock(AttachmentCacheHelper::class),
			$this->createMock(IL10N::class),
			$this->createMock(ITimeFactory::class),
			$this->createMock(IMimeTypeLoader::class),
			'alice',
		);
		$share = $this->createMock(IShare::class);
		$share->method('getSharedWith')->willReturn('42');

		$method = new \ReflectionMethod($provider, 'resolveSharesForRecipient');

		$this->assertSame([], $method->invoke($provider, [$share], 'alice'));
	}
}
