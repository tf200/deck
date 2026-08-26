<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IPreview;
use OCP\Share\IManager;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CardFileAttachmentServiceTest extends TestCase {
	private IManager&MockObject $shareManager;
	private IRootFolder&MockObject $rootFolder;
	private IPreview&MockObject $preview;
	private CardFileAttachmentService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->shareManager = $this->createMock(IManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->preview = $this->createMock(IPreview::class);
		$this->service = new CardFileAttachmentService($this->shareManager, $this->rootFolder, $this->preview);
	}

	public function testAttachExistingFileToCardCreatesDeckShareAndMetadata(): void {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(42);
		$file->method('getName')->willReturn('invoice.pdf');
		$file->method('getPath')->willReturn('/alice/Projects/invoice.pdf');
		$file->method('getMTime')->willReturn(1234);
		$file->method('getSize')->willReturn(2048);
		$file->method('getMimeType')->willReturn('application/pdf');

		$share = $this->createMock(IShare::class);
		$share->expects(self::once())->method('setNode')->with($file);
		$share->expects(self::once())->method('setShareType')->with(IShare::TYPE_DECK);
		$share->expects(self::once())->method('setSharedWith')->with('7');
		$share->expects(self::once())->method('setPermissions')->with(Constants::PERMISSION_READ);
		$share->expects(self::once())->method('setSharedBy')->with('alice');
		$share->method('getId')->willReturn('99');
		$share->method('getShareTime')->willReturn(new \DateTime('2026-01-01T00:00:00+00:00'));
		$share->method('getPermissions')->willReturn(Constants::PERMISSION_READ);

		$this->shareManager->method('newShare')->willReturn($share);
		$this->shareManager->expects(self::once())->method('createShare')->with($share)->willReturn($share);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getById')->with(42)->willReturn([$file]);
		$userFolder->method('getRelativePath')->with('/alice/Projects/invoice.pdf')->willReturn('Projects/invoice.pdf');
		$this->rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);
		$this->preview->method('isAvailable')->with($file)->willReturn(true);

		$attachment = $this->service->attachExistingFileToCard(7, $file, 'alice');

		self::assertSame(99, $attachment->getId());
		self::assertSame(7, $attachment->getCardId());
		self::assertSame('file', $attachment->getType());
		self::assertSame('alice', $attachment->getCreatedBy());
		self::assertSame('invoice.pdf', $attachment->getData());
		self::assertSame(42, $attachment->getExtendedData()['fileid']);
		self::assertSame('Projects/invoice.pdf', $attachment->getExtendedData()['path']);
	}
}
