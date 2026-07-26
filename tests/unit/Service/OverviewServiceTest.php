<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Service;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Model\CardDetails;
use Test\TestCase;

class OverviewServiceTest extends TestCase {
	public function testFindUpcomingCardsUsesFilteredEnrichedCards(): void {
		$cardService = $this->createMock(CardService::class);
		$boardMapper = $this->createMock(BoardMapper::class);
		$cardMapper = $this->createMock(CardMapper::class);
		$service = new OverviewService($cardService, $boardMapper, $cardMapper);
		$board = Board::fromParams(['id' => 10]);
		$visibleCard = Card::fromParams(['id' => 1]);
		$visibleCard->setRelatedBoard($board);
		$hiddenCard = Card::fromParams(['id' => 2]);
		$boardMapper->method('findAllForUser')->with('user1')->willReturn([$board]);
		$cardMapper->expects($this->exactly(2))
			->method('findToMeOrNotAssignedCards')
			->willReturnOnConsecutiveCalls([$visibleCard, $hiddenCard], []);
		$cardService->expects($this->once())
			->method('enrichCards')
			->with([$visibleCard, $hiddenCard])
			->willReturn([new CardDetails($visibleCard)]);

		$actual = $service->findUpcomingCards('user1');

		$this->assertSame(1, $actual['nodue'][0]['id']);
		$this->assertCount(1, $actual['nodue']);
	}
}
