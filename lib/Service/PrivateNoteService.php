<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\PrivateNote;
use OCA\Deck\Db\PrivateNoteMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\NotFoundException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Db\DoesNotExistException;

class PrivateNoteService {
	public function __construct(
		private PrivateNoteMapper $privateNoteMapper,
		private PermissionService $permissionService,
		private CardMapper $cardMapper,
		private ?string $userId,
	) {
	}

	/**
	 * @throws NoPermissionException
	 */
	public function getNotes(int $cardId): DataResponse {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);
		if ($this->userId === null) {
			throw new NoPermissionException('User must be logged in.');
		}
		$notes = $this->privateNoteMapper->findForCard($cardId, $this->userId);
		return new DataResponse($notes);
	}

	/**
	 * @throws NoPermissionException
	 */
	public function createNote(int $cardId, string $text): DataResponse {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);
		if ($this->userId === null) {
			throw new NoPermissionException('User must be logged in.');
		}

		$note = new PrivateNote();
		$note->setCardId($cardId);
		$note->setUserId($this->userId);
		$note->setText($text);
		$note->setCreatedAt(time());
		$note->setLastModified(time());

		$note = $this->privateNoteMapper->insert($note);
		return new DataResponse($note);
	}

	/**
	 * @throws NoPermissionException
	 * @throws NotFoundException
	 */
	public function updateNote(int $cardId, int $noteId, string $text): DataResponse {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);
		if ($this->userId === null) {
			throw new NoPermissionException('User must be logged in.');
		}

		try {
			$note = $this->privateNoteMapper->find($noteId);
		} catch (DoesNotExistException $e) {
			throw new NotFoundException('Note not found.');
		}

		if ($note->getUserId() !== $this->userId || $note->getCardId() !== $cardId) {
			throw new NoPermissionException('Access denied to this private note.');
		}

		$note->setText($text);
		$note->setLastModified(time());

		$note = $this->privateNoteMapper->update($note);
		return new DataResponse($note);
	}

	/**
	 * @throws NoPermissionException
	 * @throws NotFoundException
	 */
	public function deleteNote(int $cardId, int $noteId): DataResponse {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);
		if ($this->userId === null) {
			throw new NoPermissionException('User must be logged in.');
		}

		try {
			$note = $this->privateNoteMapper->find($noteId);
		} catch (DoesNotExistException $e) {
			throw new NotFoundException('Note not found.');
		}

		if ($note->getUserId() !== $this->userId || $note->getCardId() !== $cardId) {
			throw new NoPermissionException('Access denied to this private note.');
		}

		$this->privateNoteMapper->delete($note);
		return new DataResponse([]);
	}
}
