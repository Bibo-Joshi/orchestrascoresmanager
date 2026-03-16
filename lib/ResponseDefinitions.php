<?php

namespace OCA\OrchestraScoresManager;

/**
 * @psalm-type OrchestraScoresManagerScoreBookInfo = array{
 *     id: int,
 *     index: int
 * }
 * @psalm-type OrchestraScoresManagerScore = array{
 *     id: int,
 *     title: string,
 *     titleShort: string|null,
 *     composer: string|null,
 *     arranger: string|null,
 *     publisher: string|null,
 *     year: int|null,
 *     difficulty: float|null,
 *     medleyContents: list<string>|null,
 *     defects: string|null,
 *     physicalCopiesStatus: string|null,
 *     digitalStatus: string|null,
 *     gemaIds: list<string>|null,
 *     duration: int|null,
 *     tags: list<string>|null,
 *     scoreBook: OrchestraScoresManagerScoreBookInfo|null,
 *     viaScoreBook?: true
 * }
 * @psalm-type OrchestraScoresManagerScoreIndexed = array{
 *     id: int,
 *     title: string,
 *     titleShort: string|null,
 *     composer: string|null,
 *     arranger: string|null,
 *     publisher: string|null,
 *     year: int|null,
 *     difficulty: float|null,
 *     medleyContents: list<string>|null,
 *     defects: string|null,
 *     physicalCopiesStatus: string|null,
 *     digitalStatus: string|null,
 *     gemaIds: list<string>|null,
 *     duration: int|null,
 *     tags: list<string>|null,
 *     scoreBook: OrchestraScoresManagerScoreBookInfo|null,
 *     viaScoreBook?: true,
 *     index: int
 * }
 * @psalm-type OrchestraScoresManagerScoreBook = array{
 *     id: int,
 *     title: string,
 *     titleShort: string|null,
 *     composer: string|null,
 *     arranger: string|null,
 *     editor: string|null,
 *     publisher: string|null,
 *     year: int|null,
 *     difficulty: float|null,
 *     defects: string|null,
 *     physicalCopiesStatus: string|null,
 *     tags: list<string>|null,
 *     scoreCount: int
 * }
 * @psalm-type OrchestraScoresManagerScoreBookIndexed = array{
 *     id: int,
 *     title: string,
 *     titleShort: string|null,
 *     composer: string|null,
 *     arranger: string|null,
 *     editor: string|null,
 *     publisher: string|null,
 *     year: int|null,
 *     difficulty: float|null,
 *     defects: string|null,
 *     physicalCopiesStatus: string|null,
 *     tags: list<string>|null,
 *     scoreCount: int,
 *     index: int
 * }
 * @psalm-type OrchestraScoresManagerTag = array{
 *     id: int,
 *     name: string
 * }
 * @psalm-type OrchestraScoresManagerCommentAuthor = array{
 *     userId: string,
 *     displayName: string|null
 * }
 * @psalm-type OrchestraScoresManagerComment = array{
 *     id: int,
 *     content: string,
 *     creationDate: int,
 *     author: OrchestraScoresManagerCommentAuthor,
 *     scoreId: int
 * }
 * @psalm-type OrchestraScoresManagerFolderCollectionVersion = array{
 *     id: int,
 *     folderCollectionId: int,
 *     validFrom: string,
 *     validTo: string|null
 * }
 * @psalm-type OrchestraScoresManagerFolderCollection = array{
 *     id: int,
 *     title: string,
 *     description: string|null,
 *     collectionType: 'alphabetical'|'indexed',
 *     activeVersionId: int|null,
 *     scoreCount: int
 * }
 * @psalm-type OrchestraScoresManagerFolderCollectionScore = array{
 *     folderCollection: OrchestraScoresManagerFolderCollection,
 *     version: OrchestraScoresManagerFolderCollectionVersion,
 *     index: int|null,
 *     viaScoreBookId: int|null
 * }
 * @psalm-type OrchestraScoresManagerFolderCollectionScoreBook = array{
 *     folderCollection: OrchestraScoresManagerFolderCollection,
 *     version: OrchestraScoresManagerFolderCollectionVersion,
 *     index: int|null
 * }
 * @psalm-type OrchestraScoresManagerSetlist = array{
 *     id: int,
 *     title: string,
 *     description: string|null,
 *     startDateTime: string|null,
 *     duration: int|null,
 *     defaultModerationDuration: int|null,
 *     folderCollectionVersionId: int|null,
 *     isDraft: bool,
 *     isPublished: bool
 * }
 * @psalm-type OrchestraScoresManagerSetlistEntry = array{
 *     id: int,
 *     setlistId: int,
 *     index: int,
 *     comment: string|null,
 *     moderationDuration: int|null,
 *     breakDuration: int|null,
 *     scoreId: int|null
 * }
 * @psalm-suppress UnusedClass - Used for OpenAPI response type definitions
 */
class ResponseDefinitions {
}
