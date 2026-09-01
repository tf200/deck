<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Activity\ChangeSet;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Event\CardCreatedEvent;
use OCA\Deck\Event\CardDeletedEvent;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Model\CardDetails;
use OCA\Deck\Model\OptionalNullableValue;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\StatusException;
use OCA\Deck\Validators\CardServiceValidator;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class CardService {
	public function __construct(
		private CardMapper $cardMapper,
		private StackMapper $stackMapper,
		private BoardMapper $boardMapper,
		private LabelMapper $labelMapper,
		private LabelService $labelService,
		private PermissionService $permissionService,
		private CardAccessPolicyIntegration $cardAccessPolicyIntegration,
		private BoardService $boardService,
		private NotificationHelper $notificationHelper,
		private AssignmentMapper $assignedUsersMapper,
		private AttachmentService $attachmentService,
		private ActivityManager $activityManager,
		private ICommentsManager $commentsManager,
		private IUserManager $userManager,
		private ChangeHelper $changeHelper,
		private IEventDispatcher $eventDispatcher,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
		private IRequest $request,
		private CardServiceValidator $cardServiceValidator,
		private AssignmentService $assignmentService,
		private IReferenceManager $referenceManager,
		private ?string $userId,
		private ?\OCP\IL10N $l10n = null,
	) {
	}

	/**
	 * @param Card[] $cards
	 * @return CardDetails[]
	 */
	public function enrichCards(array $cards): array {
		$cards = $this->filterVisibleCards($cards);
		$user = $this->userManager->get($this->userId);

		$allCardIds = array_map(fn (Card $card) => $card->getId(), $cards);
		$attachmentCounts = $this->attachmentService->countForCards($allCardIds);

		// Pre-fetch all stacks for this batch in one query and index by ID
		$stackIds = array_unique(array_map(fn (Card $card) => $card->getStackId(), $cards));
		$stacksById = $this->stackMapper->findByIds($stackIds);

		$cardIds = array_map(function (Card $card) use ($user, $attachmentCounts, $stacksById): int {
			// Everything done in here might be heavy as it is executed for every card
			$cardId = $card->getId();
			$this->cardMapper->mapOwner($card);

			$card->setAttachmentCount($attachmentCounts[$cardId] ?? 0);

			// TODO We should find a better way just to get the comment count so we can save 1-3 queries per card here
			$countComments = $this->commentsManager->getNumberOfCommentsForObject('deckCard', (string)$card->getId());
			$lastRead = $countComments > 0 ? $this->commentsManager->getReadMark('deckCard', (string)$card->getId(), $user) : null;
			$countUnreadComments = $lastRead ? $this->commentsManager->getNumberOfCommentsForObject('deckCard', (string)$card->getId(), $lastRead) : 0;
			$card->setCommentsUnread($countUnreadComments);
			$card->setCommentsCount($countComments);

			$stack = $stacksById[$card->getStackId()] ?? $this->stackMapper->find($card->getStackId());
			$board = $this->boardService->find($stack->getBoardId(), false);
			$card->setRelatedStack($stack);
			$card->setRelatedBoard($board);

			return $card->getId();
		}, $cards);

		$assignedLabels = $this->labelMapper->findAssignedLabelsForCards($cardIds);
		$assignedUsers = $this->assignedUsersMapper->findIn($cardIds);
		$dependenciesByCard = $this->cardMapper->findDependenciesForCards($cardIds);

		// Pre-group labels and users by card ID
		$labelsByCard = [];
		foreach ($assignedLabels as $label) {
			$labelsByCard[$label->getCardId()][] = $label;
		}
		$usersByCard = [];
		foreach ($assignedUsers as $assignment) {
			$usersByCard[$assignment->getCardId()][] = $assignment;
		}

		foreach ($cards as $card) {
			$dependentCardIds = array_values(array_filter(
				$dependenciesByCard[$card->getId()] ?? [],
				function (int $dependentCardId): bool {
					try {
						$this->permissionService->checkPermission($this->cardMapper, $dependentCardId, Acl::PERMISSION_READ, $this->userId);
						return true;
					} catch (NoPermissionException $e) {
						return false;
					}
				},
			));
			$card->setLabels($labelsByCard[$card->getId()] ?? []);
			$card->setAssignedUsers($usersByCard[$card->getId()] ?? []);
			$card->setDependentCards($dependentCardIds);
		}

		return array_map(
			function (Card $card): CardDetails {
				$cardDetails = new CardDetails($card);
				$cardDetails->setCapabilities($this->cardAccessPolicyIntegration->getCapabilities($card, $this->userId));

				$references = $this->referenceManager->extractReferences($card->getTitle());
				$reference = array_shift($references);
				if ($reference) {
					$referenceData = $this->referenceManager->resolveReference($reference);
					$cardDetails->setReferenceData($referenceData);
				}

				return $cardDetails;
			},
			$cards
		);
	}

	/**
	 * @param Card[] $cards
	 * @return Card[]
	 */
	public function filterVisibleCards(array $cards): array {
		return $this->cardAccessPolicyIntegration->filterVisibleCards($cards, $this->userId);
	}

	/**
	 * Enrich and filter cards while preserving callers' raw Card response shape.
	 *
	 * @param Card[] $cards
	 * @return Card[]
	 */
	public function enrichRawCards(array $cards): array {
		$visibleCardIds = array_fill_keys(array_map(
			static fn (Card $card): int => $card->getId(),
			$this->enrichCards($cards),
		), true);

		return array_values(array_filter(
			$cards,
			static fn (Card $card): bool => isset($visibleCardIds[$card->getId()]),
		));
	}

	/** @return Card[] */
	public function fetchDeleted($boardId): array {
		$this->cardServiceValidator->check(compact('boardId'));
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		$cards = $this->cardMapper->findDeleted($boardId);
		return $this->enrichRawCards($cards);
	}

	/**
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function find(int $cardId): Card {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);
		$card = $this->cardMapper->find($cardId);
		[$card] = $this->enrichCards([$card]);

		// Attachments are only enriched on individual card fetching
		$attachments = $this->attachmentService->findAll($cardId, true);
		if ($this->request->getParam('apiVersion') === '1.0') {
			$attachments = array_filter($attachments, function ($attachment) {
				return $attachment->getType() === 'deck_file';
			});
		}
		$card->setAttachments($attachments);

		return $card;
	}

	/**
	 * @return Card[]
	 */
	public function findCalendarEntries(int $boardId): array {
		try {
			$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		} catch (NoPermissionException $e) {
			$this->logger->error('Unable to check permission for a previously obtained board ' . $boardId, ['exception' => $e]);
			return [];
		}
		return $this->filterVisibleCards($this->cardMapper->findCalendarEntries($boardId));
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadrequestException
	 */
	public function create(string $title, int $stackId, string $type, int $order, string $owner, string $description = '', $duedate = null, $startdate = null, ?string $color = null): Card {
		$this->cardServiceValidator->check(compact('title', 'stackId', 'type', 'order', 'owner'));

		$this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->stackMapper, $stackId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = new Card();
		$card->setTitle($title);
		$card->setStackId($stackId);
		$card->setType($type);
		$card->setOrder($order);
		$card->setOwner($owner);
		$card->setDescription($description);
		$card->setDuedate($duedate);
		$card->setStartdate($startdate);
		$card->setColor($color);
		$card = $this->cardMapper->insert($card);

		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_CREATE, [], $card->getOwner());
		$this->changeHelper->cardChanged($card->getId(), false);
		$this->eventDispatcher->dispatchTyped(new CardCreatedEvent($card));

		[$card] = $this->enrichCards([$card]);

		return $card;
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function delete(int $id): Card {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$this->assertCombiCardCanBeChanged($card, 'deleted');
		$card->setDeletedAt(time());
		$this->cardMapper->update($card);

		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_DELETE);
		$this->notificationHelper->markDuedateAsRead($card);
		$this->changeHelper->cardChanged($card->getId(), false);
		$this->eventDispatcher->dispatchTyped(new CardDeletedEvent($card));

		return $card;
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function update(int $id, string $title, int $stackId, string $type, string $owner, string $description = '', int $order = 0, ?string $duedate = null, ?int $deletedAt = null, ?bool $archived = null, ?OptionalNullableValue $done = null, ?string $startdate = null, ?string $color = null): Card {
		$this->cardServiceValidator->check(compact('id', 'title', 'stackId', 'type', 'owner', 'order'));

		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT, allowDeletedCard: true);
		$this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_EDIT);

		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		if (($card->getTitle() !== $title
				|| ($deletedAt !== null && $card->getDeletedAt() !== $deletedAt)
				|| ($archived !== null && $card->getArchived() !== $archived))
			&& $this->isCombiProjectCard($card)) {
			throw new NoPermissionException('Cards in a Combi project cannot be renamed, archived, deleted, or restored.');
		}
		$usesStackCompletion = $this->cardAccessPolicyIntegration->usesStackCompletion($card);
		if ($archived !== null && $card->getArchived() && $archived === true) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}

		if ($card->getDeletedAt() !== 0) {
			if ($deletedAt === null || $deletedAt > 0) {
				// Only allow operations when restoring the card
				throw new NoPermissionException('Operation not allowed. This card was deleted.');
			}
		}

		$changes = new ChangeSet($card);
		if ($card->getLastEditor() !== $this->userId && $card->getLastEditor() !== null) {
			$this->activityManager->triggerEvent(
				ActivityManager::DECK_OBJECT_CARD,
				$card,
				ActivityManager::SUBJECT_CARD_UPDATE_DESCRIPTION,
				[
					'before' => $card->getDescriptionPrev(),
					'after' => $card->getDescription()
				],
				$card->getLastEditor()
			);

			$card->setDescriptionPrev($card->getDescription());
			$card->setLastEditor($this->userId);
		}
		$card->setTitle($title);
		$card->setStackId($stackId);
		$card->setType($type);
		$card->setOrder($order);
		$card->setDuedate($duedate ? new \DateTime($duedate) : null);
		$card->setStartdate($startdate ? new \DateTime($startdate) : null);
		$card->setColor($color);
		$resetDuedateNotification = false;
		if (
			$card->getDuedate() === null
			|| ($card->getDuedate()) !== ($changes->getBefore()->getDuedate())
		) {
			$card->setNotified(false);
			$resetDuedateNotification = true;
		}

		if ($deletedAt !== null) {
			$card->setDeletedAt($deletedAt);
		}
		if ($archived !== null) {
			$card->setArchived($archived);
		}
		if (!$usesStackCompletion && $done !== null) {
			if ($done->getValue() !== null && $changes->getBefore()->getDone() === null) {
				$this->checkDependencies($card);
			}
			$card->setDone($done->getValue());
		} elseif (!$usesStackCompletion && $done === null) {
			$card->setDone(null);
		}

		if ($changes->getBefore()->getStackId() !== $stackId) {
			$this->cardAccessPolicyIntegration->assertTransition($changes->getBefore(), $stackId, $this->userId);
		}
		if (!$usesStackCompletion && $done !== null && ($changes->getBefore()->getDone() === null) !== ($done->getValue() === null)) {
			$this->cardAccessPolicyIntegration->assertAction($card, 'verify', $this->userId);
		}
		$this->applyStackCompletionTransition($card, $changes->getBefore()->getStackId(), $stackId);

		// Trigger update events before setting description as it is handled separately
		$changes->setAfter($card);
		$this->activityManager->triggerUpdateEvents(ActivityManager::DECK_OBJECT_CARD, $changes, ActivityManager::SUBJECT_CARD_UPDATE);

		if ($card->getDescriptionPrev() === null) {
			$card->setDescriptionPrev($card->getDescription());
		}
		$card->setDescription($description);

		// @var Card $card
		$card = $this->cardMapper->update($card);
		$oldBoardId = $this->stackMapper->findBoardId($changes->getBefore()->getStackId());
		$boardId = $this->cardMapper->findBoardId($card->getId());
		if ($boardId !== $oldBoardId) {
			$stack = $this->stackMapper->find($card->getStackId());
			$board = $this->boardService->find($this->cardMapper->findBoardId($card->getId()));
			$boardLabels = $board->getLabels() ?? [];
			foreach ($card->getLabels() as $cardLabel) {
				$this->removeLabel($card->getId(), $cardLabel->getId());
				$label = $this->labelMapper->find($cardLabel->getId());
				$filteredLabels = array_values(array_filter($boardLabels, fn ($item) => $item->getTitle() === $label->getTitle()));
				// clone labels that are assigned to card but don't exist in new board
				if (empty($filteredLabels)) {
					if ($this->permissionService->getPermissions($boardId)[Acl::PERMISSION_MANAGE] === true) {
						$newLabel = $this->labelService->create($label->getTitle(), $label->getColor(), $board->getId());
						$boardLabels[] = $label;
						$this->assignLabel($card->getId(), $newLabel->getId());
					}
				} else {
					$this->assignLabel($card->getId(), $filteredLabels[0]->getId());
				}
			}
			$board->setLabels($boardLabels);
			$this->boardMapper->update($board);
			$this->changeHelper->boardChanged($board->getId());
		}

		if ($resetDuedateNotification) {
			$this->notificationHelper->markDuedateAsRead($card);
		}
		$this->changeHelper->cardChanged($card->getId());

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card, $changes->getBefore()));

		[$card] = $this->enrichCards([$card]);

		return $card;
	}

	public function cloneCard(int $id, ?int $targetStackId = null):Card {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_READ);
		$originCard = $this->cardMapper->find($id);
		if ($targetStackId === null) {
			$targetStackId = $originCard->getStackId();
		}
		$this->permissionService->checkPermission($this->stackMapper, $targetStackId, Acl::PERMISSION_EDIT);
		$newCard = $this->create($originCard->getTitle(), $targetStackId, $originCard->getType(), $originCard->getOrder(), $originCard->getOwner());
		$boardId = $this->stackMapper->findBoardId($targetStackId);
		foreach ($this->labelMapper->findAssignedLabelsForCard($id) as $label) {
			if ($boardId !== $this->stackMapper->findBoardId($originCard->getStackId())) {
				try {
					$label = $this->labelService->cloneLabelIfNotExists($label->getId(), $boardId);
				} catch (NoPermissionException $e) {
					break;
				}
			}
			$this->assignLabel($newCard->getId(), $label->getId());
		}
		foreach ($this->assignedUsersMapper->findAll($id) as $assignement) {
			try {
				$this->permissionService->checkPermission($this->cardMapper, $newCard->getId(), Acl::PERMISSION_READ, $assignement->getParticipant());
			} catch (NoPermissionException $e) {
				continue;
			}
			$this->assignmentService->assignUser($newCard->getId(), $assignement->getParticipant());
		}
		$freshCard = $this->cardMapper->find($newCard->getId());
		$freshCard->setDescription($originCard->getDescription());
		$card = $this->enrichCards([$this->cardMapper->update($freshCard)]);

		return $card[0];
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function rename(int $id, string $title): Card {
		$this->cardServiceValidator->check(compact('id', 'title'));

		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$this->assertCombiCardCanBeChanged($card, 'renamed');
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$changes = new ChangeSet($card);
		$card->setTitle($title);
		$this->changeHelper->cardChanged($card->getId(), false);
		$update = $this->cardMapper->update($card);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card, $changes->getBefore()));

		return $update;
	}

	/**
	 * @return list<Card>
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function reorder(int $id, int $stackId, int $order): array {
		$this->cardServiceValidator->check(compact('id', 'stackId', 'order'));

		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		$this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_EDIT);

		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}

		$card = $this->cardMapper->find($id);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$this->cardAccessPolicyIntegration->assertTransition($card, $stackId, $this->userId);
		$changes = new ChangeSet($card);
		$oldStackId = $card->getStackId();
		$card->setStackId($stackId);

		$this->applyStackCompletionTransition($card, $oldStackId, $stackId);

		$this->cardMapper->update($card);
		$changes->setAfter($card);
		$this->activityManager->triggerUpdateEvents(ActivityManager::DECK_OBJECT_CARD, $changes, ActivityManager::SUBJECT_CARD_UPDATE);

		$cardsToReorder = $this->cardMapper->findAll($stackId);
		$result = [];
		$i = 0;
		foreach ($cardsToReorder as $cardToReorder) {
			if ($cardToReorder->getArchived()) {
				throw new StatusException('Operation not allowed. This card is archived.');
			}
			if ($cardToReorder->id === $id) {
				$cardToReorder->setOrder($order);
				$cardToReorder->setLastModified(time());
			}

			if ($i === $order) {
				$i++;
			}

			if ($cardToReorder->id !== $id) {
				$cardToReorder->setOrder($i++);
			}
			$this->cardMapper->update($cardToReorder);
			$result[$cardToReorder->getOrder()] = $cardToReorder;
		}
		$this->changeHelper->cardChanged($id, false);
		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card, $changes->getBefore()));

		return $this->filterVisibleCards(array_values($result));
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function archive(int $id): Card {
		$this->cardServiceValidator->check(compact('id'));

		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$this->assertCombiCardCanBeChanged($card, 'archived');
		$changes = new ChangeSet($card);
		$card->setArchived(true);
		$newCard = $this->cardMapper->update($card);
		$this->notificationHelper->markDuedateAsRead($card);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $newCard, ActivityManager::SUBJECT_CARD_UPDATE_ARCHIVE);
		$this->changeHelper->cardChanged($id, false);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card, $changes->getBefore()));

		return $newCard;
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function unarchive(int $id): Card {
		$this->cardServiceValidator->check(compact('id'));


		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$this->assertCombiCardCanBeChanged($card, 'unarchived');
		$changes = new ChangeSet($card);
		$card->setArchived(false);
		$newCard = $this->cardMapper->update($card);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $newCard, ActivityManager::SUBJECT_CARD_UPDATE_UNARCHIVE);
		$this->changeHelper->cardChanged($id, false);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card, $changes->getBefore()));

		return $newCard;
	}

	private function assertCombiCardCanBeChanged(Card $card, string $action): void {
		if ($this->isCombiProjectCard($card)) {
			throw new NoPermissionException("Cards in a Combi project cannot be $action.");
		}
	}

	private function isCombiProjectCard(Card $card): bool {
		$boardId = $this->cardMapper->findBoardId($card->getId());
		return $this->cardAccessPolicyIntegration->isCombiProjectBoard($boardId);
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function done(int $id): Card {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		if ($this->cardAccessPolicyIntegration->usesStackCompletion($card)) {
			throw new NoPermissionException('Move this card to the Done list to mark it as done.');
		}
		$this->cardAccessPolicyIntegration->assertAction($card, 'verify', $this->userId);
		$this->checkDependencies($card);
		$changes = new ChangeSet($card);
		$card->setDone(new \DateTime());
		$newCard = $this->cardMapper->update($card);
		// Auto-move to done column if one is configured and card is not already there
		$currentStack = $this->stackMapper->find($newCard->getStackId());
		if (!$currentStack->getIsDoneColumn()) {
			$doneStack = $this->stackMapper->findDoneColumnForBoard($currentStack->getBoardId());
			if ($doneStack !== null) {
				$newCard->setStackId($doneStack->getId());
				$newCard = $this->cardMapper->update($newCard);
			}
		}
		$this->notificationHelper->markDuedateAsRead($card);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $newCard, ActivityManager::SUBJECT_CARD_UPDATE_DONE);
		$this->changeHelper->cardChanged($id, false);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card, $changes->getBefore()));

		return $newCard;
	}

	/**
	 * @param $id
	 * @return \OCA\Deck\Db\Card
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function undone(int $id): Card {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		if ($this->cardAccessPolicyIntegration->usesStackCompletion($card)) {
			throw new NoPermissionException('Move this card out of the Done list to mark it as not done.');
		}
		$this->cardAccessPolicyIntegration->assertAction($card, 'verify', $this->userId);
		$changes = new ChangeSet($card);
		$card->setDone(null);
		$newCard = $this->cardMapper->update($card);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $newCard, ActivityManager::SUBJECT_CARD_UPDATE_UNDONE);
		$this->changeHelper->cardChanged($id, false);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card, $changes->getBefore()));

		return $newCard;
	}

	private function applyStackCompletionTransition(Card $card, int $oldStackId, int $newStackId): void {
		if ($newStackId === $oldStackId) {
			return;
		}

		$newStack = $this->stackMapper->find($newStackId);
		if ($newStack->getIsDoneColumn()) {
			if ($card->getDone() === null) {
				$this->checkDependencies($card);
				$card->setDone(new \DateTime());
			}
			return;
		}

		$oldStack = $this->stackMapper->find($oldStackId);
		if ($oldStack->getIsDoneColumn()) {
			$card->setDone(null);
		}
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function assignLabel(int $cardId, int $labelId): Card {
		return $this->assignLabelInternal($cardId, $labelId, false);
	}

	/**
	 * Assign a label while provisioning system-managed project cards.
	 *
	 * This is intentionally not exposed through a controller. User-initiated
	 * label changes must continue to use assignLabel().
	 */
	public function assignLabelForSystem(int $cardId, int $labelId): Card {
		return $this->assignLabelInternal($cardId, $labelId, true);
	}

	private function assignLabelInternal(int $cardId, int $labelId, bool $systemManaged): Card {
		$this->cardServiceValidator->check(compact('cardId', 'labelId'));

		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		$this->permissionService->checkPermission($this->labelMapper, $labelId, Acl::PERMISSION_READ);

		if ($this->boardService->isArchived($this->cardMapper, $cardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($cardId);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		if (!$systemManaged) {
			$this->assertLabelsCanBeChanged($card);
		}
		$label = $this->labelMapper->find($labelId);
		if ($label->getBoardId() !== $this->cardMapper->findBoardId($card->getId())) {
			throw new StatusException('Operation not allowed. Label does not exist.');
		}
		$this->cardMapper->assignLabel($cardId, $labelId);
		$this->changeHelper->cardChanged($cardId);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_LABEL_ASSIGN, ['label' => $label]);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));
		return $card;
	}

	/**
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function removeLabel(int $cardId, int $labelId): Card {
		$this->cardServiceValidator->check(compact('cardId', 'labelId'));


		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		$this->permissionService->checkPermission($this->labelMapper, $labelId, Acl::PERMISSION_READ);

		if ($this->boardService->isArchived($this->cardMapper, $cardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($cardId);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$this->assertLabelsCanBeChanged($card);
		$label = $this->labelMapper->find($labelId);
		$this->cardMapper->removeLabel($cardId, $labelId);
		$this->changeHelper->cardChanged($cardId);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_LABEL_UNASSING, ['label' => $label]);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));
		return $card;
	}

	private function assertLabelsCanBeChanged(Card $card): void {
		if ($this->cardAccessPolicyIntegration->isCombiProjectCard($card)) {
			throw new NoPermissionException('Tags cannot be changed on cards in a Combi project.');
		}
	}

	public function getCardUrl(int $cardId): string {
		$boardId = $this->cardMapper->findBoardId($cardId);

		return $this->urlGenerator->linkToRouteAbsolute('deck.page.indexCard', ['boardId' => $boardId, 'cardId' => $cardId]);
	}

	public function getRedirectUrlForCard(int $cardId): string {
		return $this->urlGenerator->linkToRouteAbsolute('deck.page.redirectToCard', ['cardId' => $cardId]);
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function assignDependentCard(int $cardId, int $dependentCardId): Card {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		$this->permissionService->checkPermission($this->cardMapper, $dependentCardId, Acl::PERMISSION_READ);

		if ($this->boardService->isArchived($this->cardMapper, $cardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}

		$card = $this->cardMapper->find($cardId);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}

		if ($this->cardMapper->addDependency($cardId, $dependentCardId)) {
			$this->changeHelper->cardChanged($cardId);
			$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_UPDATE);
		}

		[$card] = $this->enrichCards([$card]);
		return $card;
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function removeDependentCard(int $cardId, int $dependentCardId): Card {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		$this->permissionService->checkPermission($this->cardMapper, $dependentCardId, Acl::PERMISSION_READ);

		if ($this->boardService->isArchived($this->cardMapper, $cardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}

		$card = $this->cardMapper->find($cardId);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}

		if ($this->cardMapper->removeDependency($cardId, $dependentCardId)) {
			$this->changeHelper->cardChanged($cardId);
			$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_UPDATE);
		}

		[$card] = $this->enrichCards([$card]);
		return $card;
	}

	/**
	 * Check if all dependent cards are marked as done.
	 *
	 * @throws BadRequestException
	 */
	private function checkDependencies(Card $card): void {
		$dependentIds = $card->getDependentCards();
		if ($dependentIds === null) {
			$dependencies = $this->cardMapper->findDependenciesForCards([$card->getId()]);
			$dependentIds = $dependencies[$card->getId()] ?? [];
		}
		foreach ($dependentIds as $depId) {
			$depCard = $this->cardMapper->find($depId);
			if ($depCard->getDeletedAt() === 0 && $depCard->getDone() === null) {
				$l10n = $this->l10n ?? \OCP\Server::get(\OCP\L10N\IFactory::class)->get('deck');
				throw new BadRequestException($l10n->t('Not all dependent cards are done.'));
			}
		}
	}
}
