<?php

/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Service;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Model\CardDetails;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\StatusException;
use OCA\Deck\Validators\CardServiceValidator;
use OCP\Activity\IEvent;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

class CardServiceTest extends TestCase {

	/** @var CardService|MockObject */
	private $cardService;
	/** @var CardMapper|MockObject */
	private $cardMapper;
	/** @var StackMapper|MockObject */
	private $stackMapper;
	/** @var PermissionService|MockObject */
	private $permissionService;
	/** @var NotificationHelper */
	private $notificationHelper;
	/** @var AssignmentMapper|MockObject */
	private $assignedUsersMapper;
	/** @var BoardService|MockObject */
	private $boardService;
	/** @var LabelMapper|MockObject */
	private $labelMapper;
	/** @var LabelService|MockObject */
	private $labelService;
	private $boardMapper;
	/** @var AttachmentService|MockObject */
	private $attachmentService;
	/** @var ActivityManager|MockObject */
	private $activityManager;
	/** @var ICommentsManager|MockObject */
	private $commentsManager;
	/** @var ICommentsManager|MockObject */
	private $userManager;
	/** @var EventDispatcherInterface */
	private $eventDispatcher;
	/** @var ChangeHelper|MockObject */
	private $changeHelper;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var IRequest|MockObject */
	private $request;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var CardServiceValidator|MockObject */
	private $cardServiceValidator;
	/** @var IReferenceManager|MockObject */
	private $referenceManager;
	/** @var CardAccessPolicyIntegration|MockObject */
	private $cardAccessPolicyIntegration;
	/** @var null|int[] */
	private ?array $visibleCardIds = null;
	/** @var array{canMove: bool, canSign: bool, canVerify: bool} */
	private array $capabilities = [
		'canMove' => true,
		'canSign' => true,
		'canVerify' => true,
	];

	/** @var AssignmentService|MockObject */
	private $assignmentService;

	public function setUp(): void {
		parent::setUp();
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->labelService = $this->createMock(LabelService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->notificationHelper = $this->createMock(NotificationHelper::class);
		$this->assignedUsersMapper = $this->createMock(AssignmentMapper::class);
		$this->attachmentService = $this->createMock(AttachmentService::class);
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->changeHelper = $this->createMock(ChangeHelper::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->request = $this->createMock(IRequest::class);
		$this->cardServiceValidator = $this->createMock(CardServiceValidator::class);
		$this->assignmentService = $this->createMock(AssignmentService::class);
		$this->referenceManager = $this->createMock(IReferenceManager::class);
		$this->cardAccessPolicyIntegration = $this->createMock(CardAccessPolicyIntegration::class);
		$this->cardAccessPolicyIntegration->method('filterVisibleCards')
			->willReturnCallback(function (array $cards): array {
				if ($this->visibleCardIds === null) {
					return $cards;
				}

				return array_values(array_filter(
					$cards,
					fn (Card $card): bool => in_array($card->getId(), $this->visibleCardIds, true),
				));
			});
		$this->cardAccessPolicyIntegration->method('getCapabilities')
			->willReturnCallback(fn (): array => $this->capabilities);

		$this->logger->expects($this->any())->method('error');

		$this->cardService = new CardService(
			$this->cardMapper,
			$this->stackMapper,
			$this->boardMapper,
			$this->labelMapper,
			$this->labelService,
			$this->permissionService,
			$this->cardAccessPolicyIntegration,
			$this->boardService,
			$this->notificationHelper,
			$this->assignedUsersMapper,
			$this->attachmentService,
			$this->activityManager,
			$this->commentsManager,
			$this->userManager,
			$this->changeHelper,
			$this->eventDispatcher,
			$this->urlGenerator,
			$this->logger,
			$this->request,
			$this->cardServiceValidator,
			$this->assignmentService,
			$this->referenceManager,
			'user1'
		);
	}

	public function mockActivity($type, $object, $subject) {
		// ActivityManager::DECK_OBJECT_BOARD, $newAcl, ActivityManager::SUBJECT_BOARD_SHARE
		$event = $this->createMock(IEvent::class);
		$this->activityManager->expects($this->once())
			->method('createEvent')
			->with($type, $object, $subject)
			->willReturn($event);
		$this->activityManager->expects($this->once())
			->method('sendToUsers')
			->with($event);
	}

	public function testFind() {
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->willReturn($user);
		$this->commentsManager->expects($this->any())
			->method('getNumberOfCommentsForObject')
			->willReturn(0);
		$boardMock = $this->createMock(Board::class);
		$stackMock = new Stack();
		$stackMock->setBoardId(1234);
		$this->stackMapper->expects($this->any())
			->method('find')
			->willReturn($stackMock);
		$this->boardService->expects($this->any())
			->method('find')
			->willReturn($boardMock);
		$card = new Card();
		$card->setId(1337);
		$card->setStackId(123);
		$this->cardMapper->expects($this->any())
			->method('find')
			->with(123)
			->willReturn($card);
		$a1 = new Assignment();
		$a1->setCardId(1337);
		$a1->setType(0);
		$a1->setParticipant('user1');
		$a2 = new Assignment();
		$a2->setCardId(1337);
		$a2->setType(0);
		$a2->setParticipant('user2');
		$this->assignedUsersMapper->expects($this->any())
			->method('findIn')
			->with([1337])
			->willReturn([$a1, $a2]);
		$cardExpected = new Card();
		$cardExpected->setStackId(123);
		$cardExpected->setId(1337);
		$cardExpected->setAssignedUsers([$a1, $a2]);
		$cardExpected->setRelatedBoard($boardMock);
		$cardExpected->setRelatedStack($stackMock);
		$cardExpected->setLabels([]);
		$cardExpected->setDependentCards([]);
		$expected = new CardDetails($cardExpected);
		$expected->setCapabilities([
			'canMove' => true,
			'canSign' => true,
			'canVerify' => true,
		]);

		$actual = $this->cardService->find(123);
		$this->assertEquals($expected->jsonSerialize(), $actual->jsonSerialize());
	}

	public function testEnrichCardsSerializesCapabilitiesWithoutMutatingRawCardShape(): void {
		$card = Card::fromParams(['id' => 1, 'stackId' => 10, 'title' => 'Policy card']);
		$stack = Stack::fromParams(['id' => 10, 'boardId' => 20]);
		$board = Board::fromParams(['id' => 20]);
		$this->userManager->method('get')->willReturn($this->createMock(IUser::class));
		$this->stackMapper->method('findByIds')->with([10])->willReturn([10 => $stack]);
		$this->boardService->method('find')->with(20, false)->willReturn($board);
		$this->capabilities = [
			'canMove' => false,
			'canSign' => true,
			'canVerify' => false,
		];

		[$enriched] = $this->cardService->enrichCards([$card]);

		$this->assertSame(false, $enriched->jsonSerialize()['canMove']);
		$this->assertSame(true, $enriched->jsonSerialize()['canSign']);
		$this->assertSame(false, $enriched->jsonSerialize()['canVerify']);
		$this->assertArrayNotHasKey('canMove', $card->jsonSerialize());
		$this->assertArrayNotHasKey('canSign', $card->jsonSerialize());
		$this->assertArrayNotHasKey('canVerify', $card->jsonSerialize());
	}

	public function testEnrichCardsFiltersUnreadableDependentCardIds(): void {
		$card = Card::fromParams(['id' => 1, 'stackId' => 10]);
		$stack = Stack::fromParams(['id' => 10, 'boardId' => 20]);
		$this->userManager->method('get')->willReturn($this->createMock(IUser::class));
		$this->stackMapper->method('findByIds')->with([10])->willReturn([10 => $stack]);
		$this->boardService->method('find')->with(20, false)->willReturn(Board::fromParams(['id' => 20]));
		$this->cardMapper->method('findDependenciesForCards')->with([1])->willReturn([1 => [2, 3]]);
		$this->permissionService->expects($this->exactly(2))
			->method('checkPermission')
			->willReturnCallback(function (CardMapper $mapper, int $cardId, int $permission, ?string $userId): bool {
				if ($cardId === 3) {
					throw new NoPermissionException('Permission denied');
				}
				return true;
			});

		[$enriched] = $this->cardService->enrichCards([$card]);

		$this->assertSame([2], $enriched->getDependentCards());
	}

	public function testFetchDeletedFiltersCardsAndPreservesRawShape(): void {
		$visibleCard = Card::fromParams(['id' => 1, 'stackId' => 10]);
		$hiddenCard = Card::fromParams(['id' => 2, 'stackId' => 10]);
		$stack = Stack::fromParams(['id' => 10, 'boardId' => 20]);
		$board = Board::fromParams(['id' => 20]);
		$this->visibleCardIds = [1];
		$this->cardMapper->expects($this->once())
			->method('findDeleted')
			->with(20)
			->willReturn([$visibleCard, $hiddenCard]);
		$this->userManager->method('get')->willReturn($this->createMock(IUser::class));
		$this->stackMapper->method('findByIds')->with([10])->willReturn([10 => $stack]);
		$this->boardService->method('find')->with(20, false)->willReturn($board);

		$actual = $this->cardService->fetchDeleted(20);

		$this->assertSame([$visibleCard], $actual);
		$this->assertNotInstanceOf(CardDetails::class, $actual[0]);
	}

	public function testFindCalendarEntriesFiltersCardsWithoutChangingShape(): void {
		$visibleCard = Card::fromParams(['id' => 1]);
		$hiddenCard = Card::fromParams(['id' => 2]);
		$this->visibleCardIds = [1];
		$this->cardMapper->expects($this->once())
			->method('findCalendarEntries')
			->with(20)
			->willReturn([$visibleCard, $hiddenCard]);

		$actual = $this->cardService->findCalendarEntries(20);

		$this->assertSame([$visibleCard], $actual);
		$this->assertNotInstanceOf(CardDetails::class, $actual[0]);
	}

	public function testCreate() {
		$card = Card::fromParams([
			'title' => 'Card title',
			'owner' => 'admin',
			'stackId' => 123,
			'order' => 999,
			'type' => 'text',
			'id' => 0,
			'color' => '00ff00',
		]);
		$stack = Stack::fromParams([
			'id' => 123,
			'boardId' => 1337,
		]);
		$this->cardMapper->expects($this->once())
			->method('insert')
			->willReturn($card);
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($stack);
		$b = $this->cardService->create('Card title', 123, 'text', 999, 'admin', '', null, null, '00ff00');

		$this->assertEquals($b->getTitle(), 'Card title');
		$this->assertEquals($b->getOwner(), 'admin');
		$this->assertEquals($b->getType(), 'text');
		$this->assertEquals($b->getOrder(), 999);
		$this->assertEquals($b->getStackId(), 123);
		$this->assertEquals($b->getColor(), '00ff00');
	}

	public function testClone() {
		$card = new Card();
		$card->setId(1);
		$card->setTitle('Card title');
		$card->setType('test');
		$card->setOrder(0);
		$card->setOwner('admin');
		$card->setStackId(12345);
		$card->setDescription('A test description');

		$clonedCard = clone $card;
		$clonedCard->setId(2);
		$clonedCard->setStackId(1234);

		$this->cardMapper->expects($this->exactly(2))
			->method('insert')
			->willReturn($card, $clonedCard);

		$this->cardMapper->expects($this->once())
			->method('update')->willReturn($clonedCard);

		$this->cardMapper->expects($this->exactly(3))
			->method('find')
			->willReturn($card, $clonedCard, $clonedCard);

		$this->cardMapper->expects($this->any())
			->method('findBoardId')
			->willReturn(1234);

		$this->labelMapper->expects($this->any())
			->method('find')
			->willReturn(Label::fromRow([
				'id' => 1,
				'boardId' => 1234,
			]));

		// check if users are assigned
		$this->assignmentService->expects($this->once())
			->method('assignUser')
			->with(2, 'admin');
		$a1 = new Assignment();
		$a1->setCardId(2);
		$a1->setType(0);
		$a1->setParticipant('admin');
		$this->assignedUsersMapper->expects($this->once())
			->method('findAll')
			->with(1)
			->willReturn([$a1]);

		$this->assignedUsersMapper->expects($this->any())
			->method('findIn')
			->willReturn([]);

		// check if labels get cloned
		$label = new Label();
		$label->setId(1);
		$this->labelMapper->expects($this->once())
			->method('findAssignedLabelsForCard')
			->willReturn([$label]);
		$this->cardMapper->expects($this->once())
			->method('assignLabel')
			->with($clonedCard->getId(), $label->getId());

		$labelForClone = Label::fromRow([
			'id' => 1,
			'boardId' => 1234,
			'cardId' => 2,
		]);
		$this->labelMapper->expects($this->any())
			->method('findAssignedLabelsForCards')
			->willReturn([$labelForClone]);

		$stackMock = new Stack();
		$stackMock->setBoardId(1234);
		$this->stackMapper->expects($this->any())
			->method('find')
			->willReturn($stackMock);

		$b = $this->cardService->create('Card title', 123, 'text', 999, 'admin');
		$c = $this->cardService->cloneCard($b->getId(), 1234);

		$this->assertEquals($b->getTitle(), $c->getTitle());
		$this->assertEquals($b->getOwner(), $c->getOwner());
		$this->assertNotEquals($b->getStackId(), $c->getStackId());

		$this->assertEquals('A test description', $c->getDescription());

		$this->assertCount(1, $c->getLabels());
		$this->assertEquals($label->getId(), $c->getLabels()[0]->getId());
	}

	public function testDelete() {
		$cardToBeDeleted = new Card();
		$this->cardMapper->expects($this->once())
			->method('find')
			->willReturn($cardToBeDeleted);
		$this->cardMapper->expects($this->once())
			->method('update')
			->willReturn($cardToBeDeleted);
		$this->cardService->delete(123);
		$this->assertTrue($cardToBeDeleted->getDeletedAt() <= time(), 'deletedAt is in the past');
	}

	public function testDeleteIsDeniedForCombiProjectCard(): void {
		$card = new Card();
		$card->setId(123);
		$this->cardMapper->method('find')->willReturn($card);
		$this->cardMapper->method('findBoardId')->with(123)->willReturn(2);
		$this->cardAccessPolicyIntegration->method('isCombiProjectBoard')->with(2)->willReturn(true);
		$this->cardMapper->expects($this->never())->method('update');

		$this->expectException(NoPermissionException::class);
		$this->cardService->delete(123);
	}

	public function testMetadataUpdateDoesNotAssertTransition(): void {
		$card = Card::fromParams([
			'title' => 'Card title',
			'archived' => 'false',
			'stackId' => 234,
			'color' => '00ff00',
		]);
		$stack = Stack::fromParams([
			'id' => 234,
			'boardId' => 1337,
		]);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function ($c) {
			$c->setId(1);
			return $c;
		});
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(234)
			->willReturn($stack);
		$this->cardAccessPolicyIntegration->expects($this->never())->method('assertTransition');
		$this->cardAccessPolicyIntegration->expects($this->never())->method('assertAction');
		$actual = $this->cardService->update(123, 'newtitle', 234, 'text', 'admin', 'foo', 999, '2017-01-01 00:00:00', null, null, null, null, 'ffffff');
		$this->assertEquals('newtitle', $actual->getTitle());
		$this->assertEquals(234, $actual->getStackId());
		$this->assertEquals('text', $actual->getType());
		$this->assertEquals(999, $actual->getOrder());
		$this->assertEquals('foo', $actual->getDescription());
		$this->assertEquals(new \DateTime('2017-01-01T00:00:00+00:00'), $actual->getDuedate());
		$this->assertEquals('ffffff', $actual->getColor());
	}

	public function testUpdateWithStartdate() {
		$card = Card::fromParams([
			'title' => 'Card title',
			'archived' => 'false',
			'stackId' => 234,
		]);
		$stack = Stack::fromParams([
			'id' => 234,
			'boardId' => 1337,
		]);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function ($c) {
			$c->setId(1);
			return $c;
		});
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(234)
			->willReturn($stack);
		$actual = $this->cardService->update(123, 'newtitle', 234, 'text', 'admin', 'foo', 999, '2017-01-01 00:00:00', null, null, null, '2016-12-15 00:00:00');
		$this->assertEquals('newtitle', $actual->getTitle());
		$this->assertEquals(new \DateTime('2017-01-01T00:00:00+00:00'), $actual->getDuedate());
		$this->assertEquals(new \DateTime('2016-12-15T00:00:00+00:00'), $actual->getStartdate());
	}

	public function testUpdateArchived() {
		$card = new Card();
		$card->setTitle('title');
		$card->setArchived(true);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->never())->method('update');
		$this->expectException(StatusException::class);
		$this->cardService->update(123, 'newtitle', 234, 'text', 'admin', 'foo', 999, '2017-01-01 00:00:00', null, true);
	}

	public function testRename() {
		$card = new Card();
		$card->setTitle('title');
		$card->setArchived(false);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function ($c) {
			return $c;
		});
		$actual = $this->cardService->rename(123, 'newtitle');
		$this->assertEquals('newtitle', $actual->getTitle());
	}

	public function testRenameArchived() {
		$card = new Card();
		$card->setTitle('title');
		$card->setArchived(true);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->never())->method('update');
		$this->expectException(StatusException::class);
		$this->cardService->rename(123, 'newtitle');
	}

	public static function dataReorder() {
		return [
			[0, 0, [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]],
			[0, 9, [1, 2, 3, 4, 5, 6, 7, 8, 9, 0]],
			[1, 3, [0, 2, 3, 1, 4, 5, 6, 7, 8, 9]]
		];
	}
	/** @dataProvider dataReorder */
	public function testReorder($cardId, $newPosition, $order) {
		$cards = $this->getCards();
		$cardsTmp = [];
		$this->cardMapper->expects($this->once())->method('findAll')->willReturn($cards);
		$card = new Card();
		$card->setStackId(123);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardAccessPolicyIntegration->expects($this->once())
			->method('assertTransition')
			->with($card, 123, 'user1');
		$result = $this->cardService->reorder($cardId, 123, $newPosition);
		foreach ($result as $card) {
			$actual[$card->getOrder()] = $card->getId();
		}
		$this->assertEquals($order, $actual);
	}

	public function testReorderFiltersResponseAndPreservesRawCards(): void {
		$visibleCard = Card::fromParams(['id' => 1, 'stackId' => 10, 'order' => 0]);
		$hiddenCard = Card::fromParams(['id' => 2, 'stackId' => 10, 'order' => 1]);
		$this->visibleCardIds = [1];
		$this->cardMapper->method('find')->with(1)->willReturn($visibleCard);
		$this->cardMapper->method('findAll')->with(10)->willReturn([$visibleCard, $hiddenCard]);

		$actual = $this->cardService->reorder(1, 10, 0);

		$this->assertSame([$visibleCard], $actual);
		$this->assertNotInstanceOf(CardDetails::class, $actual[0]);
	}

	private function getCards() {
		$cards = [];
		for ($i = 0; $i < 10; $i++) {
			$cards[$i] = new Card();
			$cards[$i]->setTitle($i);
			$cards[$i]->setOrder($i);
			$cards[$i]->setId($i);
		}
		return $cards;
	}

	public function testReorderArchived() {
		$card = new Card();
		$card->setTitle('title');
		$card->setArchived(true);
		$card->setStackId(123);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->never())->method('update')->willReturnCallback(function ($c) {
			return $c;
		});
		$this->expectException(StatusException::class);
		$actual = $this->cardService->reorder(123, 234, 1);
	}

	public function testReorderIntoDoneColumnMarksCardDone(): void {
		$card = new Card();
		$card->setId(42);
		$card->setStackId(10);
		$card->setDependentCards([]);
		$doneStack = new Stack();
		$doneStack->setId(20);
		$doneStack->setIsDoneColumn(true);

		$this->cardMapper->method('find')->with(42)->willReturn($card);
		$this->cardMapper->method('findAll')->with(20)->willReturn([$card]);
		$this->stackMapper->method('find')->with(20)->willReturn($doneStack);

		$this->cardService->reorder(42, 20, 0);

		$this->assertNotNull($card->getDone());
		$this->assertSame(20, $card->getStackId());
	}

	public function testReorderOutOfDoneColumnMarksCardNotDone(): void {
		$card = new Card();
		$card->setId(42);
		$card->setStackId(20);
		$card->setDone(new \DateTime());
		$targetStack = new Stack();
		$targetStack->setId(10);
		$targetStack->setIsDoneColumn(false);
		$doneStack = new Stack();
		$doneStack->setId(20);
		$doneStack->setIsDoneColumn(true);

		$this->cardMapper->method('find')->with(42)->willReturn($card);
		$this->cardMapper->method('findAll')->with(10)->willReturn([$card]);
		$this->stackMapper->method('find')->willReturnMap([
			[10, $targetStack],
			[20, $doneStack],
		]);

		$this->cardService->reorder(42, 10, 0);

		$this->assertNull($card->getDone());
		$this->assertSame(10, $card->getStackId());
	}

	public function testArchive() {
		$card = new Card();
		$this->assertFalse($card->getArchived());
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function ($c) {
			return $c;
		});
		$this->assertTrue($this->cardService->archive(123)->getArchived());
	}
	public function testUnarchive() {
		$card = new Card();
		$card->setArchived(true);
		$this->assertTrue($card->getArchived());
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function ($c) {
			return $c;
		});
		$this->assertFalse($this->cardService->unarchive(123)->getArchived());
	}

	public function testAssignLabel() {
		$card = new Card();
		$card->setArchived(false);
		$card->setId(123);
		$label = new Label();
		$label->setBoardId(1);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('assignLabel');
		$this->cardMapper->expects($this->once())
			->method('findBoardId')
			->willReturn(1);
		$this->labelMapper->expects($this->once())
			->method('find')
			->willReturn($label);
		$this->cardService->assignLabel(123, 999);
	}

	public function testAssignLabelArchived() {
		$card = new Card();
		$card->setArchived(true);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->never())->method('assignLabel');
		$this->expectException(StatusException::class);
		$this->cardService->assignLabel(123, 999);
	}

	public function testAssignLabelRejectedForCombiProjectCard(): void {
		$card = new Card();
		$card->setId(123);
		$this->cardMapper->method('find')->with(123)->willReturn($card);
		$this->cardAccessPolicyIntegration->expects($this->once())
			->method('isCombiProjectCard')
			->with($card)
			->willReturn(true);
		$this->cardMapper->expects($this->never())->method('assignLabel');
		$this->labelMapper->expects($this->never())->method('find');

		$this->expectException(NoPermissionException::class);
		$this->cardService->assignLabel(123, 999);
	}

	public function testSystemAssignLabelAllowedForCombiProjectCard(): void {
		$card = new Card();
		$card->setArchived(false);
		$card->setId(123);
		$label = new Label();
		$label->setBoardId(1);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('findBoardId')->willReturn(1);
		$this->cardMapper->expects($this->once())->method('assignLabel')->with(123, 999);
		$this->labelMapper->expects($this->once())->method('find')->willReturn($label);
		$this->cardAccessPolicyIntegration->expects($this->never())->method('isCombiProjectCard');

		$this->cardService->assignLabelForSystem(123, 999);
	}

	public function testSystemAssignLabelRejectsLabelFromAnotherBoard(): void {
		$card = new Card();
		$card->setArchived(false);
		$card->setId(123);
		$label = new Label();
		$label->setBoardId(2);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('findBoardId')->willReturn(1);
		$this->cardMapper->expects($this->never())->method('assignLabel');
		$this->labelMapper->expects($this->once())->method('find')->willReturn($label);

		$this->expectException(StatusException::class);
		$this->cardService->assignLabelForSystem(123, 999);
	}

	public function testRemoveLabel() {
		$card = new Card();
		$card->setArchived(false);
		$card->setId(123);
		$label = new Label();
		$label->setBoardId(1);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('removeLabel');
		$this->labelMapper->expects($this->once())
			->method('find')
			->willReturn($label);
		$this->cardService->removeLabel(123, 999);
	}

	public function testRemoveLabelArchived() {
		$card = new Card();
		$card->setArchived(true);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->never())->method('removeLabel');
		$this->expectException(StatusException::class);
		$this->cardService->removeLabel(123, 999);
	}

	public function testRemoveLabelRejectedForCombiProjectCard(): void {
		$card = new Card();
		$card->setId(123);
		$this->cardMapper->method('find')->with(123)->willReturn($card);
		$this->cardAccessPolicyIntegration->expects($this->once())
			->method('isCombiProjectCard')
			->with($card)
			->willReturn(true);
		$this->cardMapper->expects($this->never())->method('removeLabel');
		$this->labelMapper->expects($this->never())->method('find');

		$this->expectException(NoPermissionException::class);
		$this->cardService->removeLabel(123, 999);
	}

	public function testDoneMarksCardAsDone(): void {
		$card = new Card();
		$card->setId(42);
		$card->setStackId(10);
		$stack = new Stack();
		$stack->setId(10);
		$stack->setBoardId(1);
		$stack->setIsDoneColumn(false);
		$this->cardMapper->expects($this->once())
			->method('find')
			->with(42)
			->willReturn($card);
		$this->cardMapper->expects($this->once())
			->method('update')
			->willReturnCallback(fn (Card $c) => $c);
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(10)
			->willReturn($stack);
		$this->stackMapper->expects($this->once())
			->method('findDoneColumnForBoard')
			->with(1)
			->willReturn(null);
		$this->cardAccessPolicyIntegration->expects($this->once())
			->method('usesStackCompletion')
			->with($card)
			->willReturn(false);
		$this->cardAccessPolicyIntegration->expects($this->once())
			->method('assertAction')
			->with($card, 'verify', 'user1');
		$result = $this->cardService->done(42);
		$this->assertNotNull($result->getDone());
		$this->assertEquals(10, $result->getStackId());
	}

	public function testUndoneAssertsVerify(): void {
		$card = new Card();
		$card->setId(42);
		$card->setDone(new \DateTime());
		$this->cardMapper->expects($this->once())
			->method('find')
			->with(42)
			->willReturn($card);
		$this->cardMapper->expects($this->once())
			->method('update')
			->with($card)
			->willReturn($card);
		$this->cardAccessPolicyIntegration->expects($this->once())
			->method('usesStackCompletion')
			->with($card)
			->willReturn(false);
		$this->cardAccessPolicyIntegration->expects($this->once())
			->method('assertAction')
			->with($card, 'verify', 'user1');

		$result = $this->cardService->undone(42);

		$this->assertNull($result->getDone());
	}

	public function testDoneAutoMovesToDoneColumn(): void {
		$card = new Card();
		$card->setId(42);
		$card->setStackId(10);
		$currentStack = new Stack();
		$currentStack->setId(10);
		$currentStack->setBoardId(1);
		$currentStack->setIsDoneColumn(false);
		$doneStack = new Stack();
		$doneStack->setId(20);
		$doneStack->setBoardId(1);
		$doneStack->setIsDoneColumn(true);
		$this->cardMapper->expects($this->once())
			->method('find')
			->with(42)
			->willReturn($card);
		$this->cardMapper->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(fn (Card $c) => $c);
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(10)
			->willReturn($currentStack);
		$this->stackMapper->expects($this->once())
			->method('findDoneColumnForBoard')
			->with(1)
			->willReturn($doneStack);
		$result = $this->cardService->done(42);
		$this->assertNotNull($result->getDone());
		$this->assertEquals(20, $result->getStackId());
	}

	public function testDoneDoesNotMoveCardAlreadyInDoneColumn(): void {
		$card = new Card();
		$card->setId(42);
		$card->setStackId(20);
		$doneStack = new Stack();
		$doneStack->setId(20);
		$doneStack->setBoardId(1);
		$doneStack->setIsDoneColumn(true);
		$this->cardMapper->expects($this->once())
			->method('find')
			->with(42)
			->willReturn($card);
		$this->cardMapper->expects($this->once())
			->method('update')
			->willReturnCallback(fn (Card $c) => $c);
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(20)
			->willReturn($doneStack);
		$this->stackMapper->expects($this->never())
			->method('findDoneColumnForBoard');
		$result = $this->cardService->done(42);
		$this->assertNotNull($result->getDone());
		$this->assertEquals(20, $result->getStackId());
	}

	public function testAssignDependentCard() {
		$card = Card::fromParams([
			'id' => 42,
			'title' => 'Card title',
			'stackId' => 234,
		]);
		$stack = Stack::fromParams([
			'id' => 234,
			'boardId' => 1337,
		]);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('addDependency')->with(42, 43)->willReturn(true);
		$this->cardMapper->expects($this->once())->method('findDependenciesForCards')->with([42])->willReturn([42 => [44, 43]]);
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(234)
			->willReturn($stack);
		$result = $this->cardService->assignDependentCard(42, 43);
		$this->assertEquals([44, 43], $result->getDependentCards());
	}

	public function testRemoveDependentCard() {
		$card = Card::fromParams([
			'id' => 42,
			'title' => 'Card title',
			'stackId' => 234,
		]);
		$stack = Stack::fromParams([
			'id' => 234,
			'boardId' => 1337,
		]);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('removeDependency')->with(42, 43)->willReturn(true);
		$this->cardMapper->expects($this->once())->method('findDependenciesForCards')->with([42])->willReturn([42 => [44]]);
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(234)
			->willReturn($stack);
		$result = $this->cardService->removeDependentCard(42, 43);
		$this->assertEquals([44], $result->getDependentCards());
	}
}
