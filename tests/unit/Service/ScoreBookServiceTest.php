<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\unit\Service;

use OCA\OrchestraScoresManager\Db\Score;
use OCA\OrchestraScoresManager\Db\ScoreBook;
use OCA\OrchestraScoresManager\Db\ScoreBookMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookScoreLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreBookTagLinkMapper;
use OCA\OrchestraScoresManager\Db\ScoreMapper;
use OCA\OrchestraScoresManager\Db\Tag;
use OCA\OrchestraScoresManager\Db\TagMapper;
use OCA\OrchestraScoresManager\Policy\PolicyInterface;
use OCA\OrchestraScoresManager\Policy\ScoreBookPolicy;
use OCA\OrchestraScoresManager\Service\AuthorizationService;
use OCA\OrchestraScoresManager\Service\ScoreBookService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ScoreBookService.
 */
final class ScoreBookServiceTest extends TestCase {
	private ScoreBookMapper $scoreBookMapper;
	private ScoreBookScoreLinkMapper $scoreBookScoreLinkMapper;
	private ScoreBookTagLinkMapper $scoreBookTagLinkMapper;
	private ScoreMapper $scoreMapper;
	private TagMapper $tagMapper;
	private AuthorizationService $authorizationService;
	private ScoreBookPolicy $scoreBookPolicy;
	private IL10N $l10n;
	private ScoreBookService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->scoreBookMapper = $this->createMock(ScoreBookMapper::class);
		$this->scoreBookScoreLinkMapper = $this->createMock(ScoreBookScoreLinkMapper::class);
		$this->scoreBookTagLinkMapper = $this->createMock(ScoreBookTagLinkMapper::class);
		$this->scoreMapper = $this->createMock(ScoreMapper::class);
		$this->tagMapper = $this->createMock(TagMapper::class);
		$this->authorizationService = $this->createMock(AuthorizationService::class);
		$this->scoreBookPolicy = $this->createMock(ScoreBookPolicy::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->l10n->method('t')->willReturnCallback(fn ($text, $params = []) => vsprintf($text, $params));

		$this->service = new ScoreBookService(
			$this->scoreBookMapper,
			$this->scoreBookScoreLinkMapper,
			$this->scoreBookTagLinkMapper,
			$this->scoreMapper,
			$this->tagMapper,
			$this->authorizationService,
			$this->scoreBookPolicy,
			$this->l10n
		);
	}

	public function testGetScoreBookByIdRequiresAuthorization(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setId(1);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->scoreBookPolicy, PolicyInterface::ACTION_READ);

		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($scoreBook);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('countScoresInScoreBook')
			->with(1)
			->willReturn(5);

		$result = $this->service->getScoreBookById(1);

		$this->assertIsArray($result);
		$this->assertSame(5, $result['scoreCount']);
	}

	public function testGetAllScoreBooksEnrichesWithScoreCount(): void {
		$scoreBook1 = new ScoreBook();
		$scoreBook1->setId(1);
		$scoreBook2 = new ScoreBook();
		$scoreBook2->setId(2);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->scoreBookMapper->expects($this->once())
			->method('findAll')
			->willReturn([$scoreBook1, $scoreBook2]);

		$this->scoreBookScoreLinkMapper->expects($this->exactly(2))
			->method('countScoresInScoreBook')
			->willReturnCallback(fn ($id) => $id === 1 ? 3 : 7);

		$result = $this->service->getAllScoreBooks();

		$this->assertCount(2, $result);
		$this->assertSame(3, $result[0]['scoreCount']);
		$this->assertSame(7, $result[1]['scoreCount']);
	}

	public function testCreateScoreBookRequiresAuthorization(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setTitle('Test Book');
		$createdBook = clone $scoreBook;
		$createdBook->setId(1);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->scoreBookPolicy, PolicyInterface::ACTION_CREATE);

		$this->scoreBookMapper->expects($this->once())
			->method('insert')
			->with($scoreBook)
			->willReturn($createdBook);

		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($createdBook);

		$result = $this->service->createScoreBook($scoreBook);

		$this->assertIsArray($result);
		$this->assertSame(0, $result['scoreCount']);
	}

	public function testCreateScoreBookWithTags(): void {
		$scoreBook = new ScoreBook();
		$createdBook = clone $scoreBook;
		$createdBook->setId(1);

		$tag = new Tag();
		$tag->setId(10);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->scoreBookMapper->expects($this->once())
			->method('insert')
			->willReturn($createdBook);

		$this->tagMapper->expects($this->once())
			->method('find')
			->with(10)
			->willReturn($tag);

		$this->scoreBookTagLinkMapper->expects($this->once())
			->method('setTagsForScoreBook')
			->with(1, [10]);

		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->willReturn($createdBook);

		$this->service->createScoreBook($scoreBook, [10]);
	}

	public function testUpdateScoreBookRequiresAuthorization(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setId(1);
		$scoreBook->setTitle('Updated Book');

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->scoreBookPolicy, PolicyInterface::ACTION_UPDATE, $scoreBook);

		$this->scoreBookMapper->expects($this->once())
			->method('update')
			->with($scoreBook);

		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->willReturn($scoreBook);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('countScoresInScoreBook')
			->willReturn(0);

		$this->service->updateScoreBook($scoreBook);
	}

	public function testDeleteScoreBookRequiresAuthorization(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setId(1);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->scoreBookPolicy, PolicyInterface::ACTION_DELETE);

		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($scoreBook);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('countScoresInScoreBook')
			->with(1)
			->willReturn(0);

		$this->scoreBookTagLinkMapper->expects($this->once())
			->method('deleteAllTagsForScoreBook')
			->with(1);

		$this->scoreBookMapper->expects($this->once())
			->method('delete')
			->with($scoreBook);

		$this->service->deleteScoreBook(1);
	}

	public function testDeleteScoreBookThrowsWhenHasLinkedScores(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setId(1);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->willReturn($scoreBook);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('countScoresInScoreBook')
			->willReturn(5);

		$this->scoreBookMapper->expects($this->never())
			->method('delete');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Cannot delete score book with linked scores');

		$this->service->deleteScoreBook(1);
	}

	public function testGetScoresInScoreBookRequiresAuthorization(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setId(1);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->scoreBookPolicy, PolicyInterface::ACTION_READ);

		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($scoreBook);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('findScoresForScoreBook')
			->with(1)
			->willReturn([
				['score_id' => 10, 'index' => 1],
				['score_id' => 20, 'index' => 2],
			]);

		$score1 = new Score();
		$score1->setId(10);
		$score2 = new Score();
		$score2->setId(20);

		$this->scoreMapper->expects($this->once())
			->method('findMultiple')
			->with([10, 20])
			->willReturn([$score1, $score2]);

		$result = $this->service->getScoresInScoreBook(1);

		$this->assertCount(2, $result);
		$this->assertSame(10, $result[0]->getId());
		$this->assertSame(20, $result[1]->getId());
	}

	public function testAddScoreToScoreBookRequiresAuthorization(): void {
		$scoreBook = new ScoreBook();
		$scoreBook->setId(1);
		$score = new Score();
		$score->setId(10);

		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->scoreBookPolicy, PolicyInterface::ACTION_UPDATE);

		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->willReturn($scoreBook);

		$this->scoreMapper->expects($this->once())
			->method('find')
			->with(10)
			->willReturn($score);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('findScoreBookForScore')
			->with(10)
			->willReturn(null);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('isIndexOccupied')
			->with(1, 5)
			->willReturn(false);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('addScoreToScoreBook')
			->with(1, 10, 5);

		$this->service->addScoreToScoreBook(1, 10, 5);
	}

	public function testAddScoreToScoreBookThrowsWhenScoreAlreadyInBook(): void {
		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->willReturn(new ScoreBook());

		$this->scoreMapper->expects($this->once())
			->method('find')
			->willReturn(new Score());

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('findScoreBookForScore')
			->willReturn(['score_book_id' => 2, 'index' => 1]);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Score is already part of a score book');

		$this->service->addScoreToScoreBook(1, 10, 5);
	}

	public function testAddScoreToScoreBookThrowsWhenIndexOccupied(): void {
		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->willReturn(new ScoreBook());

		$this->scoreMapper->expects($this->once())
			->method('find')
			->willReturn(new Score());

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('findScoreBookForScore')
			->willReturn(null);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('isIndexOccupied')
			->willReturn(true);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Index 5 is already occupied');

		$this->service->addScoreToScoreBook(1, 10, 5);
	}

	public function testAddScoresToScoreBookValidatesAll(): void {
		$this->authorizationService->expects($this->once())
			->method('authorizePolicy');

		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->willReturn(new ScoreBook());

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('findScoresForScoreBook')
			->willReturn([]);

		$this->scoreMapper->expects($this->exactly(2))
			->method('find')
			->willReturn(new Score());

		$this->scoreBookScoreLinkMapper->expects($this->exactly(2))
			->method('findScoreBookForScore')
			->willReturn(null);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('addScoresToScoreBook')
			->with(1, [
				['score_id' => 10, 'index' => 1],
				['score_id' => 20, 'index' => 2],
			]);

		$scores = [
			['scoreId' => 10, 'index' => 1],
			['scoreId' => 20, 'index' => 2],
		];

		$this->service->addScoresToScoreBook(1, $scores);
	}

	public function testRemoveScoreFromScoreBookRequiresAuthorization(): void {
		$this->authorizationService->expects($this->once())
			->method('authorizePolicy')
			->with($this->scoreBookPolicy, PolicyInterface::ACTION_UPDATE);

		$this->scoreBookScoreLinkMapper->expects($this->once())
			->method('removeScoreFromScoreBook')
			->with(1, 10);

		$this->service->removeScoreFromScoreBook(1, 10);
	}

	public function testFindScoreBookEntityThrowsWhenNotFound(): void {
		$this->scoreBookMapper->expects($this->once())
			->method('find')
			->willThrowException(new DoesNotExistException('Not found'));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Score book not found');

		$this->service->findScoreBookEntity(999);
	}
}
