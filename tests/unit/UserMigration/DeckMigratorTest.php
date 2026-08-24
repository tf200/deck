<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\UserMigration;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\Importer\BoardImportService;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Service\ShareFileAttachmentExportService;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Files\IAppData;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class DeckMigratorTest extends TestCase {
	/** @var BoardMapper|MockObject */
	private $boardMapper;
	/** @var StackMapper|MockObject */
	private $stackMapper;
	/** @var CardMapper|MockObject */
	private $cardMapper;
	/** @var LabelMapper|MockObject */
	private $labelMapper;
	/** @var AclMapper|MockObject */
	private $aclMapper;
	/** @var AssignmentMapper|MockObject */
	private $assignmentMapper;
	/** @var AttachmentMapper|MockObject */
	private $attachmentMapper;
	/** @var ICommentsManager|MockObject */
	private $commentsManager;
	/** @var IAppData|MockObject */
	private $appData;
	/** @var ShareFileAttachmentExportService|MockObject */
	private $shareFileAttachmentExportService;
	/** @var BoardService|MockObject */
	private $boardService;
	/** @var BoardImportService|MockObject */
	private $boardImportService;
	/** @var PermissionService|MockObject */
	private $permissionService;
	private DeckMigrator $migrator;

	public function setUp(): void {
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->assignmentMapper = $this->createMock(AssignmentMapper::class);
		$this->attachmentMapper = $this->createMock(AttachmentMapper::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->appData = $this->createMock(IAppData::class);
		$this->shareFileAttachmentExportService = $this->createMock(ShareFileAttachmentExportService::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->boardImportService = $this->createMock(BoardImportService::class);
		$this->permissionService = $this->createMock(PermissionService::class);

		$this->migrator = new DeckMigrator(
			$this->createMock(IL10N::class),
			$this->boardMapper,
			$this->stackMapper,
			$this->cardMapper,
			$this->labelMapper,
			$this->aclMapper,
			$this->assignmentMapper,
			$this->attachmentMapper,
			$this->commentsManager,
			$this->appData,
			$this->shareFileAttachmentExportService,
			$this->boardService,
			$this->boardImportService,
			$this->permissionService,
		);
	}

	public function testExportWritesBoardsJson(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');

		$board = new Board();
		$board->setId(42);
		$board->setTitle('Board A');

		$this->boardMapper->expects($this->once())
			->method('findAllByUser')
			->with('admin')
			->willReturn([$board]);
		$this->boardService->expects($this->once())
			->method('setUserId')
			->with('admin');
		$this->permissionService->expects($this->once())
			->method('setUserId')
			->with('admin');
		$this->boardService->expects($this->once())
			->method('export')
			->with(42)
			->willReturn($board);

		$destination = $this->createMock(IExportDestination::class);
		$destination->expects($this->once())
			->method('addFileContents')
			->with(
				'boards.json',
				$this->callback(static function (string $json): bool {
					$decoded = json_decode($json, true);
					return is_array($decoded) && isset($decoded['boards']) && count($decoded['boards']) === 1;
				})
			);

		$this->migrator->export($user, $destination, $this->createMock(OutputInterface::class));
	}

	public function testExportSkipsDeletedBoards(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');

		$deletedBoard = new Board();
		$deletedBoard->setId(5202);
		$deletedBoard->setTitle('Deleted board');
		$deletedBoard->setDeletedAt(time());

		$this->boardMapper->expects($this->once())
			->method('findAllByUser')
			->with('admin')
			->willReturn([$deletedBoard]);
		$this->boardService->expects($this->once())
			->method('setUserId')
			->with('admin');
		$this->permissionService->expects($this->once())
			->method('setUserId')
			->with('admin');
		$this->boardService->expects($this->never())
			->method('export');

		$destination = $this->createMock(IExportDestination::class);
		$destination->expects($this->once())
			->method('addFileContents')
			->with(
				'boards.json',
				$this->callback(static function (string $json): bool {
					$decoded = json_decode($json, true);
					return is_array($decoded) && isset($decoded['boards']) && count($decoded['boards']) === 0;
				})
			);

		$this->migrator->export($user, $destination, $this->createMock(OutputInterface::class));
	}

	public function testExportFiltersArchivedCardsByPolicy(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$board = new Board();
		$board->setId(42);
		$stack = new Stack();
		$stack->setId(7);
		$stack->setCards([]);
		$board->setStacks([$stack]);
		$visibleCard = new Card();
		$visibleCard->setId(10);
		$visibleCard->setStackId(7);
		$hiddenCard = new Card();
		$hiddenCard->setId(11);
		$hiddenCard->setStackId(7);

		$this->boardMapper->method('findAllByUser')->with('admin')->willReturn([$board]);
		$this->boardService->method('export')->with(42)->willReturn($board);
		$this->cardMapper->method('findAllArchivedForStacks')->with([7])->willReturn([7 => [$visibleCard, $hiddenCard]]);
		$this->permissionService->expects($this->exactly(2))
			->method('checkPermission')
			->with($this->cardMapper, $this->anything(), Acl::PERMISSION_READ, 'admin')
			->willReturnCallback(static function (CardMapper $mapper, int $cardId): bool {
				if ($cardId === 11) {
					throw new NoPermissionException('Hidden by card policy');
				}
				return true;
			});
		$this->commentsManager->method('getForObject')->willReturn(new \ArrayIterator([]));
		$this->shareFileAttachmentExportService->method('exportCardAttachments')->willReturn([]);

		$destination = $this->createMock(IExportDestination::class);
		$destination->expects($this->once())
			->method('addFileContents')
			->with('boards.json', $this->callback(static function (string $json): bool {
				$cards = json_decode($json, true)['boards'][0]['stacks'][0]['cards'] ?? [];
				return array_column($cards, 'id') === [10];
			}));

		$this->migrator->export($user, $destination, $this->createMock(OutputInterface::class));
	}

	public function testImportSkipsWhenNoVersion(): void {
		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->with('deck')->willReturn(null);

		$this->boardImportService->expects($this->never())->method('import');

		$this->migrator->import(
			$this->createMock(IUser::class),
			$source,
			$this->createMock(OutputInterface::class),
		);
	}

	public function testMigrationFormatVersionCompatibility(): void {
		$this->assertSame(2, $this->migrator->getVersion());

		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->with('deck')->willReturnOnConsecutiveCalls(1, 2, 3);

		$this->assertTrue($this->migrator->canImport($source));
		$this->assertTrue($this->migrator->canImport($source));
		$this->assertFalse($this->migrator->canImport($source));
	}

	public function testExportIncludesCommentNoteType(): void {
		$user = $this->createConfiguredMock(IUser::class, ['getUID' => 'admin']);
		$board = new Board();
		$board->setId(42);
		$stack = new Stack();
		$stack->setId(7);
		$card = new Card();
		$card->setId(10);
		$card->setStackId(7);
		$stack->setCards([$card]);
		$board->setStacks([$stack]);

		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn('5');
		$comment->method('getParentId')->willReturn('0');
		$comment->method('getActorType')->willReturn('users');
		$comment->method('getActorId')->willReturn('admin');
		$comment->method('getMessage')->willReturn('Decision made');
		$comment->method('getCreationDateTime')->willReturn(new \DateTime('2026-08-05T10:00:00+00:00'));
		$comment->method('getObjectType')->willReturn('deckCard');
		$comment->method('getObjectId')->willReturn('10');
		$comment->method('getVerb')->willReturn('comment');
		$comment->method('getMetaData')->willReturn(['deck.noteType' => 'decision']);

		$this->boardMapper->method('findAllByUser')->with('admin')->willReturn([$board]);
		$this->boardService->method('export')->with(42)->willReturn($board);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([]);
		$this->commentsManager->method('getForObject')->with('deckCard', '10')->willReturn(new \ArrayIterator([$comment]));
		$this->shareFileAttachmentExportService->method('exportCardAttachments')->willReturn([]);

		$destination = $this->createMock(IExportDestination::class);
		$destination->expects($this->once())->method('addFileContents')->with(
			'boards.json',
			$this->callback(static function (string $json): bool {
				$comments = json_decode($json, true)['boards'][0]['stacks'][0]['cards'][0]['comments'] ?? [];
				return ($comments[0]['noteType'] ?? null) === 'decision';
			}),
		);

		$this->migrator->export($user, $destination, $this->createMock(OutputInterface::class));
	}

	public function testImportConfiguresServiceAndImports(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->with('deck')->willReturn(1);
		$source->method('getFileContents')->with('boards.json')->willReturn('{"boards":[{"id":1,"title":"Board A","stacks":[]}]}');

		$this->permissionService->expects($this->once())
			->method('setUserId')
			->with('alice');
		$this->permissionService->expects($this->once())
			->method('canCreate')
			->willReturn(true);

		$this->boardImportService->expects($this->once())->method('setSystem')->with('DeckJson');
		$this->boardImportService->expects($this->once())
			->method('setConfigInstance')
			->with($this->callback(static function (\stdClass $config): bool {
				return isset($config->owner, $config->uidRelation) && $config->owner === 'alice';
			}));
		$this->boardImportService->expects($this->once())
			->method('setData')
			->with($this->callback(static function (\stdClass $data): bool {
				return isset($data->boards) && is_array($data->boards) && count($data->boards) === 1;
			}));
		$this->boardImportService->expects($this->once())->method('import');

		$this->migrator->import($user, $source, $this->createMock(OutputInterface::class));
	}
}
