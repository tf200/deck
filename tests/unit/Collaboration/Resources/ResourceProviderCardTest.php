<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Collaboration\Resources;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\PermissionService;
use OCP\Collaboration\Resources\IResource;
use OCP\Collaboration\Resources\ResourceException;
use OCP\IURLGenerator;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

class ResourceProviderCardTest extends TestCase {
	private CardMapper $cardMapper;
	private PermissionService $permissionService;
	private ResourceProviderCard $provider;

	protected function setUp(): void {
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->provider = new ResourceProviderCard(
			$this->cardMapper,
			$this->createMock(BoardMapper::class),
			$this->permissionService,
			$this->createMock(IURLGenerator::class),
		);
	}

	public function testCanAccessResourceRequiresCardReadForUser(): void {
		$resource = $this->createMock(IResource::class);
		$resource->method('getType')->willReturn(ResourceProviderCard::RESOURCE_TYPE);
		$resource->method('getId')->willReturn('123');
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('recipient');
		$this->permissionService->expects(self::once())
			->method('checkPermission')
			->with($this->cardMapper, '123', Acl::PERMISSION_READ, 'recipient')
			->willThrowException(new NoPermissionException('Permission denied'));

		self::assertFalse($this->provider->canAccessResource($resource, $user));
	}

	public function testRichObjectSuppressesUnreadableCard(): void {
		$resource = $this->createMock(IResource::class);
		$resource->method('getId')->willReturn('123');
		$this->permissionService->expects(self::once())
			->method('checkPermission')
			->with($this->cardMapper, '123', Acl::PERMISSION_READ)
			->willThrowException(new NoPermissionException('Permission denied'));
		$this->cardMapper->expects(self::never())->method('find');
		$this->expectException(ResourceException::class);

		$this->provider->getResourceRichObject($resource);
	}
}
