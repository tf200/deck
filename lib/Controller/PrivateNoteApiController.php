<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\PrivateNoteService;
use OCA\Deck\StatusException;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class PrivateNoteApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private PrivateNoteService $privateNoteService,
		string $corsMethods = 'PUT, POST, GET, DELETE, PATCH',
		string $corsAllowedHeaders = 'Authorization, Content-Type, Accept',
		int $corsMaxAge = 1728000,
	) {
		parent::__construct($appName, $request, $corsMethods, $corsAllowedHeaders, $corsMaxAge);
	}

	/**
	 * @throws StatusException
	 */
	#[NoAdminRequired]
	public function listNotes(int $cardId): DataResponse {
		try {
			return $this->privateNoteService->getNotes($cardId);
		} catch (\Exception $e) {
			throw new StatusException(500, $e->getMessage(), $e);
		}
	}

	/**
	 * @throws StatusException
	 */
	#[NoAdminRequired]
	public function createNote(int $cardId, string $text = ''): DataResponse {
		try {
			return $this->privateNoteService->createNote($cardId, $text);
		} catch (\Exception $e) {
			throw new StatusException(500, $e->getMessage(), $e);
		}
	}

	/**
	 * @throws StatusException
	 */
	#[NoAdminRequired]
	public function updateNote(int $cardId, int $noteId, string $text = ''): DataResponse {
		try {
			return $this->privateNoteService->updateNote($cardId, $noteId, $text);
		} catch (\Exception $e) {
			throw new StatusException(500, $e->getMessage(), $e);
		}
	}

	/**
	 * @throws StatusException
	 */
	#[NoAdminRequired]
	public function deleteNote(int $cardId, int $noteId): DataResponse {
		try {
			return $this->privateNoteService->deleteNote($cardId, $noteId);
		} catch (\Exception $e) {
			throw new StatusException(500, $e->getMessage(), $e);
		}
	}
}
