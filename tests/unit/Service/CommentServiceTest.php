<?php

declare(strict_types=1);

namespace OCA\Deck\Tests\Unit\Service;

use OCA\Deck\Db\CardMapper;
use OCA\Deck\Service\CommentService;
use OCA\Deck\Service\CommentType;
use OCA\Deck\Service\PermissionService;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CommentServiceTest extends TestCase {
	public function testCreatePersistsAndReturnsNoteType(): void {
		$comment = $this->comment(
			['custom' => 'value'],
			['custom' => 'value', CommentType::METADATA_KEY => 'decision'],
		);
		$comment->expects($this->once())
			->method('setMetaData')
			->with(['custom' => 'value', CommentType::METADATA_KEY => 'decision']);

		$commentsManager = $this->createMock(ICommentsManager::class);
		$commentsManager->method('create')->with('users', 'alice', 'deckCard', '12')->willReturn($comment);
		$commentsManager->expects($this->once())->method('save')->with($comment);

		$response = $this->service($commentsManager)->create(12, 'Decision made', 0, 'decision');

		$this->assertSame('decision', $response->getData()['noteType']);
	}

	public function testUpdateWithoutTypePreservesExistingMetadata(): void {
		$comment = $this->comment([CommentType::METADATA_KEY => 'customer']);
		$comment->expects($this->never())->method('setMetaData');

		$commentsManager = $this->createMock(ICommentsManager::class);
		$commentsManager->method('get')->with('5')->willReturn($comment);
		$commentsManager->expects($this->once())->method('save')->with($comment);

		$response = $this->service($commentsManager)->update(12, 5, 'Updated message');

		$this->assertSame('customer', $response->getData()['noteType']);
	}

	private function service(ICommentsManager $commentsManager): CommentService {
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('checkPermission')->willReturn(true);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('getDisplayName')->with('alice')->willReturn('Alice');

		return new CommentService(
			$commentsManager,
			$permissionService,
			$this->createMock(CardMapper::class),
			$userManager,
			$this->createMock(LoggerInterface::class),
			'alice',
		);
	}

	/**
	 * @param array<string, mixed> $metadata
	 * @param array<string, mixed>|null $savedMetadata
	 */
	private function comment(array $metadata, ?array $savedMetadata = null): IComment {
		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn('5');
		$comment->method('getObjectType')->willReturn('deckCard');
		$comment->method('getObjectId')->willReturn('12');
		$comment->method('getMessage')->willReturn('Decision made');
		$comment->method('getActorId')->willReturn('alice');
		$comment->method('getActorType')->willReturn('users');
		$comment->method('getCreationDateTime')->willReturn(new \DateTime('2026-08-05T10:00:00+00:00'));
		$comment->method('getMentions')->willReturn([]);
		$comment->method('getParentId')->willReturn('0');
		$comment->method('getMetaData')->willReturnOnConsecutiveCalls($metadata, $savedMetadata ?? $metadata);
		return $comment;
	}
}
