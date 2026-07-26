<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Service;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Search\FilterStringParser;
use OCA\Deck\Search\Query\SearchQuery;
use OCP\Comments\ICommentsManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Test\TestCase;

class SearchServiceTest extends TestCase {
	public function testSearchCommentsOmitsCommentsOnHiddenCards(): void {
		$boardService = $this->createMock(BoardService::class);
		$cardMapper = $this->createMock(CardMapper::class);
		$cardService = $this->createMock(CardService::class);
		$commentsManager = $this->createMock(ICommentsManager::class);
		$filterStringParser = $this->createMock(FilterStringParser::class);
		$userManager = $this->createMock(IUserManager::class);
		$l10n = $this->createMock(IL10N::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$service = new SearchService(
			$boardService,
			$cardMapper,
			$cardService,
			$commentsManager,
			$filterStringParser,
			$userManager,
			$l10n,
			$urlGenerator,
		);
		$boardService->method('getUserBoards')->willReturn([Board::fromParams(['id' => 10])]);
		$query = new SearchQuery();
		$filterStringParser->method('parse')->with('hidden')->willReturn($query);
		$cardMapper->method('searchComments')->willReturn([[
			'comment_id' => '11',
			'id' => 1,
			'title' => 'Hidden card',
		]]);
		$commentsManager->expects($this->never())->method('get');
		$cardService->expects($this->once())
			->method('enrichRawCards')
			->with($this->callback(static fn (array $cards): bool => count($cards) === 1
				&& $cards[0] instanceof Card
				&& $cards[0]->getId() === 1))
			->willReturn([]);

		$this->assertSame([], $service->searchComments('hidden'));
	}
}
