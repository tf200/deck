<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Attachment;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IPreview;
use OCP\Share\IManager;
use OCP\Share\IShare;

/**
 * Creates a Deck file attachment for a file that is already in Files.
 *
 * This is used by integrations that prepare a file before attaching it, such
 * as projectcreatoraio's OCR staging and finalization flow.
 */
class CardFileAttachmentService {
	public function __construct(
		private readonly IManager $shareManager,
		private readonly IRootFolder $rootFolder,
		private readonly IPreview $preview,
	) {
	}

	public function attachExistingFileToCard(int $cardId, File $file, string $userId): Attachment {
		$share = $this->shareManager->newShare();
		$share->setNode($file);
		$share->setShareType(IShare::TYPE_DECK);
		$share->setSharedWith((string)$cardId);
		$share->setPermissions(Constants::PERMISSION_READ);
		$share->setSharedBy($userId);
		$share = $this->shareManager->createShare($share);

		$attachment = new Attachment();
		$attachment->setType('file');
		$attachment->setId((int)$share->getId());
		$attachment->setCardId($cardId);
		$attachment->setCreatedBy($userId);
		$attachment->setData($file->getName());
		$attachment->setLastModified($file->getMTime());
		$attachment->setCreatedAt($share->getShareTime()->getTimestamp());
		$attachment->setDeletedAt(0);

		$userFolder = $this->rootFolder->getUserFolder($userId);
		$files = $userFolder->getById($file->getId());
		if ($files !== []) {
			$resolvedFile = array_shift($files);
			$attachment->setExtendedData([
				'path' => $userFolder->getRelativePath($resolvedFile->getPath()),
				'fileid' => $resolvedFile->getId(),
				'data' => $resolvedFile->getName(),
				'filesize' => $resolvedFile->getSize(),
				'mimetype' => $resolvedFile->getMimeType(),
				'info' => pathinfo($resolvedFile->getName()),
				'hasPreview' => $this->preview->isAvailable($resolvedFile),
				'permissions' => $share->getPermissions(),
			]);
		}

		return $attachment;
	}
}
