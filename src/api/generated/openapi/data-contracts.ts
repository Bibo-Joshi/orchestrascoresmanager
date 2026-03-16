/* eslint-disable */
/* tslint:disable */
// @ts-nocheck
/*
 * ---------------------------------------------------------------
 * ## THIS FILE WAS GENERATED VIA SWAGGER-TYPESCRIPT-API        ##
 * ##                                                           ##
 * ## AUTHOR: acacode                                           ##
 * ## SOURCE: https://github.com/acacode/swagger-typescript-api ##
 * ---------------------------------------------------------------
 */

export interface Comment {
  /** @format int64 */
  id: number;
  content: string;
  /** @format int64 */
  creationDate: number;
  author: CommentAuthor;
  /** @format int64 */
  scoreId: number;
}

export interface CommentAuthor {
  userId: string;
  displayName: string | null;
}

export interface FolderCollection {
  /** @format int64 */
  id: number;
  title: string;
  description: string | null;
  collectionType: "alphabetical" | "indexed";
  /** @format int64 */
  activeVersionId: number | null;
  /** @format int64 */
  scoreCount: number;
}

export interface FolderCollectionScore {
  folderCollection: FolderCollection;
  version: FolderCollectionVersion;
  /** @format int64 */
  index: number | null;
  /** @format int64 */
  viaScoreBookId: number | null;
}

export interface FolderCollectionScoreBook {
  folderCollection: FolderCollection;
  version: FolderCollectionVersion;
  /** @format int64 */
  index: number | null;
}

export interface FolderCollectionVersion {
  /** @format int64 */
  id: number;
  /** @format int64 */
  folderCollectionId: number;
  validFrom: string;
  validTo: string | null;
}

export interface OCSMeta {
  status: string;
  statuscode: number;
  message?: string;
  totalitems?: string;
  itemsperpage?: string;
}

export interface Score {
  /** @format int64 */
  id: number;
  title: string;
  titleShort: string | null;
  composer: string | null;
  arranger: string | null;
  publisher: string | null;
  /** @format int64 */
  year: number | null;
  /** @format double */
  difficulty: number | null;
  medleyContents: string[] | null;
  defects: string | null;
  physicalCopiesStatus: string | null;
  digitalStatus: string | null;
  gemaIds: string[] | null;
  /** @format int64 */
  duration: number | null;
  tags: string[] | null;
  scoreBook: ScoreBookInfo | null;
  viaScoreBook?: true;
}

export interface ScoreBook {
  /** @format int64 */
  id: number;
  title: string;
  titleShort: string | null;
  composer: string | null;
  arranger: string | null;
  editor: string | null;
  publisher: string | null;
  /** @format int64 */
  year: number | null;
  /** @format double */
  difficulty: number | null;
  defects: string | null;
  physicalCopiesStatus: string | null;
  tags: string[] | null;
  /** @format int64 */
  scoreCount: number;
}

export interface ScoreBookIndexed {
  /** @format int64 */
  id: number;
  title: string;
  titleShort: string | null;
  composer: string | null;
  arranger: string | null;
  editor: string | null;
  publisher: string | null;
  /** @format int64 */
  year: number | null;
  /** @format double */
  difficulty: number | null;
  defects: string | null;
  physicalCopiesStatus: string | null;
  tags: string[] | null;
  /** @format int64 */
  scoreCount: number;
  /** @format int64 */
  index: number;
}

export interface ScoreBookInfo {
  /** @format int64 */
  id: number;
  /** @format int64 */
  index: number;
}

export interface ScoreIndexed {
  /** @format int64 */
  id: number;
  title: string;
  titleShort: string | null;
  composer: string | null;
  arranger: string | null;
  publisher: string | null;
  /** @format int64 */
  year: number | null;
  /** @format double */
  difficulty: number | null;
  medleyContents: string[] | null;
  defects: string | null;
  physicalCopiesStatus: string | null;
  digitalStatus: string | null;
  gemaIds: string[] | null;
  /** @format int64 */
  duration: number | null;
  tags: string[] | null;
  scoreBook: ScoreBookInfo | null;
  viaScoreBook?: true;
  /** @format int64 */
  index: number;
}

export interface Setlist {
  /** @format int64 */
  id: number;
  title: string;
  description: string | null;
  startDateTime: string | null;
  /** @format int64 */
  duration: number | null;
  /** @format int64 */
  defaultModerationDuration: number | null;
  /** @format int64 */
  folderCollectionVersionId: number | null;
  isDraft: boolean;
  isPublished: boolean;
}

export interface SetlistEntry {
  /** @format int64 */
  id: number;
  /** @format int64 */
  setlistId: number;
  /** @format int64 */
  index: number;
  comment: string | null;
  /** @format int64 */
  moderationDuration: number | null;
  /** @format int64 */
  breakDuration: number | null;
  /** @format int64 */
  scoreId: number | null;
}

export interface Tag {
  /** @format int64 */
  id: number;
  name: string;
}

export interface CommentApiGetCommentData {
  ocs: {
    meta: OCSMeta;
    data: Comment;
  };
}

export interface CommentApiDeleteCommentData {
  ocs: {
    meta: OCSMeta;
    data: object;
  };
}

export interface FolderCollectionApiGetFolderCollectionsData {
  ocs: {
    meta: OCSMeta;
    data: FolderCollection[];
  };
}

export interface FolderCollectionApiPostFolderCollectionPayload {
  /** The title of the folder collection */
  title: string;
  /** The type of the folder collection (alphabetical or indexed) */
  collectionType: "alphabetical" | "indexed";
  /**
   * The description of the folder collection
   * @default null
   */
  description?: string | null;
  /**
   * The start date for the initial version (Y-m-d format), defaults to today
   * @default null
   */
  validFrom?: string | null;
}

export interface FolderCollectionApiPostFolderCollectionData {
  ocs: {
    meta: OCSMeta;
    data: FolderCollection;
  };
}

export interface FolderCollectionApiGetFolderCollectionData {
  ocs: {
    meta: OCSMeta;
    data: FolderCollection;
  };
}

export interface FolderCollectionApiPatchFolderCollectionPayload {
  /**
   * The title of the folder collection
   * @default null
   */
  title?: string | null;
  /**
   * The description of the folder collection
   * @default null
   */
  description?: string | null;
}

export interface FolderCollectionApiPatchFolderCollectionData {
  ocs: {
    meta: OCSMeta;
    data: FolderCollection;
  };
}

export interface FolderCollectionApiDeleteFolderCollectionData {
  ocs: {
    meta: OCSMeta;
    data: object;
  };
}

export interface FolderCollectionApiGetFolderCollectionScoresData {
  ocs: {
    meta: OCSMeta;
    data: ScoreIndexed[] | Score[];
  };
}

export interface FolderCollectionApiPostFolderCollectionScorePayload {
  /**
   * The ID of the score to add
   * @format int64
   */
  scoreId: number;
  /**
   * The index position (required for indexed collections)
   * @format int64
   * @default null
   */
  index?: number | null;
}

export interface FolderCollectionApiPostFolderCollectionScoreData {
  ocs: {
    meta: OCSMeta;
    data: object;
  };
}

export interface FolderCollectionApiDeleteFolderCollectionScoreData {
  ocs: {
    meta: OCSMeta;
    data: object;
  };
}

export interface FolderCollectionApiGetFolderCollectionScoreBooksData {
  ocs: {
    meta: OCSMeta;
    data: ScoreBookIndexed[] | ScoreBook[];
  };
}

export interface FolderCollectionApiPostFolderCollectionScoreBookPayload {
  /**
   * The ID of the score book to add
   * @format int64
   */
  scoreBookId: number;
  /**
   * The index position (required for indexed collections)
   * @format int64
   * @default null
   */
  index?: number | null;
}

export interface FolderCollectionApiPostFolderCollectionScoreBookData {
  ocs: {
    meta: OCSMeta;
    data: object;
  };
}

export interface FolderCollectionApiDeleteFolderCollectionScoreBookData {
  ocs: {
    meta: OCSMeta;
    data: object;
  };
}

export interface FolderCollectionApiGetFolderCollectionVersionsData {
  ocs: {
    meta: OCSMeta;
    data: FolderCollectionVersion[];
  };
}

export interface FolderCollectionApiPostFolderCollectionVersionPayload {
  /** The start date of the version (Y-m-d format) */
  validFrom: string;
  /**
   * The end date of the version (Y-m-d format) or null for active version
   * @default null
   */
  validTo?: string | null;
  /**
   * Optional version ID to copy scores/scorebooks from
   * @format int64
   * @default null
   */
  copyFromVersionId?: number | null;
}

export interface FolderCollectionApiPostFolderCollectionVersionData {
  ocs: {
    meta: OCSMeta;
    data: FolderCollectionVersion;
  };
}

export interface FolderCollectionApiStartNewVersionPayload {
  /**
   * The start date for the new version (Y-m-d format), defaults to today
   * @default null
   */
  validFrom?: string | null;
}

export interface FolderCollectionApiStartNewVersionData {
  ocs: {
    meta: OCSMeta;
    data: FolderCollectionVersion;
  };
}

export interface FolderCollectionVersionApiGetFolderCollectionVersionData {
  ocs: {
    meta: OCSMeta;
    data: FolderCollectionVersion;
  };
}

export interface FolderCollectionVersionApiPatchFolderCollectionVersionPayload {
  /**
   * The new end date (Y-m-d format) to deactivate the version
   * @default null
   */
  validTo?: string | null;
}

export interface FolderCollectionVersionApiPatchFolderCollectionVersionData {
  ocs: {
    meta: OCSMeta;
    data: FolderCollectionVersion;
  };
}

export interface ScoreApiGetScoresData {
  ocs: {
    meta: OCSMeta;
    data: Score[];
  };
}

export interface ScoreApiPostScorePayload {
  /** The title of the score */
  title: string;
  /**
   * The short title of the score
   * @default null
   */
  titleShort?: string | null;
  /**
   * The composer of the score
   * @default null
   */
  composer?: string | null;
  /**
   * The arranger of the score
   * @default null
   */
  arranger?: string | null;
  /**
   * The publisher of the score
   * @default null
   */
  publisher?: string | null;
  /**
   * The year of publication
   * @format int64
   * @default null
   */
  year?: number | null;
  /**
   * The difficulty level of the score
   * @format double
   * @default null
   */
  difficulty?: number | null;
  /**
   * The contents of the medley
   * @default null
   */
  medleyContents?: string[] | null;
  /**
   * Any defects of the score
   * @default null
   */
  defects?: string | null;
  /**
   * The status of physical copies
   * @default null
   */
  physicalCopiesStatus?: string | null;
  /**
   * The digital status of the score
   * @default null
   */
  digitalStatus?: string | null;
  /**
   * The GEMA IDs of the score
   * @default null
   */
  gemaIds?: string[] | null;
  /**
   * The duration of the score in seconds
   * @format int64
   * @default null
   */
  duration?: number | null;
  /**
   * optional list of tag ids to link
   * @default null
   */
  tagIds?: number[] | null;
  /**
   * Score book info with id and index (both required)
   * @default null
   */
  scoreBook?: {
    /** @format int64 */
    id: number;
    /** @format int64 */
    index: number;
  } | null;
}

export interface ScoreApiPostScoreData {
  ocs: {
    meta: OCSMeta;
    data: Score;
  };
}

export interface ScoreApiPatchScorePayload {
  /**
   * The title of the score
   * @default null
   */
  title?: string | null;
  /**
   * The short title of the score
   * @default null
   */
  titleShort?: string | null;
  /**
   * The composer of the score
   * @default null
   */
  composer?: string | null;
  /**
   * The arranger of the score
   * @default null
   */
  arranger?: string | null;
  /**
   * The publisher of the score
   * @default null
   */
  publisher?: string | null;
  /**
   * The year of publication
   * @format int64
   * @default null
   */
  year?: number | null;
  /**
   * The difficulty level of the score
   * @format double
   * @default null
   */
  difficulty?: number | null;
  /**
   * The contents of the medley
   * @default null
   */
  medleyContents?: string[] | null;
  /**
   * Any defects of the score
   * @default null
   */
  defects?: string | null;
  /**
   * The status of physical copies
   * @default null
   */
  physicalCopiesStatus?: string | null;
  /**
   * The digital status of the score
   * @default null
   */
  digitalStatus?: string | null;
  /**
   * The GEMA IDs of the score
   * @default null
   */
  gemaIds?: string[] | null;
  /**
   * The duration of the score in seconds
   * @format int64
   * @default null
   */
  duration?: number | null;
  /**
   * optional list of tag ids to link
   * @default null
   */
  tagIds?: number[] | null;
  /**
   * Score book info (id and/or index). Set id to null to remove from book.
   * @default null
   */
  scoreBook?: {
    /** @format int64 */
    id?: number;
    /** @format int64 */
    index?: number;
  } | null;
}

export interface ScoreApiPatchScoreData {
  ocs: {
    meta: OCSMeta;
    data: Score;
  };
}

export type ScoreApiDeleteScoreData = any;

export interface ScoreApiGetScoreCommentsData {
  ocs: {
    meta: OCSMeta;
    data: Comment[];
  };
}

export interface ScoreApiPostScoreCommentPayload {
  /** The content of the comment */
  content: string;
  /** The user ID of the commenter */
  userId: string;
  /**
   * The creation date timestamp
   * @format int64
   */
  creationDate: number;
}

export interface ScoreApiPostScoreCommentData {
  ocs: {
    meta: OCSMeta;
    data: Comment;
  };
}

export interface ScoreApiGetScoreFolderCollectionsData {
  ocs: {
    meta: OCSMeta;
    data: FolderCollectionScore[];
  };
}

export interface ScoreBookApiGetScoreBooksData {
  ocs: {
    meta: OCSMeta;
    data: ScoreBook[];
  };
}

export interface ScoreBookApiPostScoreBookPayload {
  /** The title of the score book */
  title: string;
  /**
   * The short title of the score book
   * @default null
   */
  titleShort?: string | null;
  /**
   * The composer of the score book
   * @default null
   */
  composer?: string | null;
  /**
   * The arranger of the score book
   * @default null
   */
  arranger?: string | null;
  /**
   * The editor of the score book
   * @default null
   */
  editor?: string | null;
  /**
   * The publisher of the score book
   * @default null
   */
  publisher?: string | null;
  /**
   * The year of publication
   * @format int64
   * @default null
   */
  year?: number | null;
  /**
   * The difficulty level
   * @format double
   * @default null
   */
  difficulty?: number | null;
  /**
   * Any defects
   * @default null
   */
  defects?: string | null;
  /**
   * The status of physical copies
   * @default null
   */
  physicalCopiesStatus?: string | null;
  /**
   * optional list of tag ids to link
   * @default null
   */
  tagIds?: number[] | null;
}

export interface ScoreBookApiPostScoreBookData {
  ocs: {
    meta: OCSMeta;
    data: ScoreBook;
  };
}

export interface ScoreBookApiGetScoreBookData {
  ocs: {
    meta: OCSMeta;
    data: ScoreBook;
  };
}

export interface ScoreBookApiPatchScoreBookPayload {
  /**
   * The title of the score book
   * @default null
   */
  title?: string | null;
  /**
   * The short title of the score book
   * @default null
   */
  titleShort?: string | null;
  /**
   * The composer of the score book
   * @default null
   */
  composer?: string | null;
  /**
   * The arranger of the score book
   * @default null
   */
  arranger?: string | null;
  /**
   * The editor of the score book
   * @default null
   */
  editor?: string | null;
  /**
   * The publisher of the score book
   * @default null
   */
  publisher?: string | null;
  /**
   * The year of publication
   * @format int64
   * @default null
   */
  year?: number | null;
  /**
   * The difficulty level
   * @format double
   * @default null
   */
  difficulty?: number | null;
  /**
   * Any defects
   * @default null
   */
  defects?: string | null;
  /**
   * The status of physical copies
   * @default null
   */
  physicalCopiesStatus?: string | null;
  /**
   * optional list of tag ids to link
   * @default null
   */
  tagIds?: number[] | null;
}

export interface ScoreBookApiPatchScoreBookData {
  ocs: {
    meta: OCSMeta;
    data: ScoreBook;
  };
}

export interface ScoreBookApiDeleteScoreBookData {
  ocs: {
    meta: OCSMeta;
    data: object;
  };
}

export interface ScoreBookApiGetScoreBookScoresData {
  ocs: {
    meta: OCSMeta;
    data: Score[];
  };
}

export interface ScoreBookApiPostScoreBookScorePayload {
  /**
   * The ID of the score to add
   * @format int64
   */
  scoreId: number;
  /**
   * The index position for the score in the book
   * @format int64
   */
  index: number;
}

export interface ScoreBookApiPostScoreBookScoreData {
  ocs: {
    meta: OCSMeta;
    data: object;
  };
}

export interface ScoreBookApiPostScoreBookScoresBatchPayload {
  /** Array of score data with scoreId and index */
  scores: {
    /** @format int64 */
    scoreId: number;
    /** @format int64 */
    index: number;
  }[];
}

export interface ScoreBookApiPostScoreBookScoresBatchData {
  ocs: {
    meta: OCSMeta;
    data: object;
  };
}

export interface ScoreBookApiDeleteScoreBookScoreData {
  ocs: {
    meta: OCSMeta;
    data: object;
  };
}

export interface ScoreBookApiGetScoreBookFolderCollectionsData {
  ocs: {
    meta: OCSMeta;
    data: FolderCollectionScoreBook[];
  };
}

export interface SetlistApiGetSetlistsData {
  ocs: {
    meta: OCSMeta;
    data: Setlist[];
  };
}

export interface SetlistApiPostSetlistPayload {
  /** The title of the setlist */
  title: string;
  /**
   * The description of the setlist
   * @default null
   */
  description?: string | null;
  /**
   * The start date and time (ISO 8601 format)
   * @default null
   */
  startDateTime?: string | null;
  /**
   * The duration in seconds
   * @format int64
   * @default null
   */
  duration?: number | null;
  /**
   * The default moderation duration in seconds
   * @format int64
   * @default null
   */
  defaultModerationDuration?: number | null;
  /**
   * The ID of the folder collection version
   * @format int64
   * @default null
   */
  folderCollectionVersionId?: number | null;
  /**
   * Whether the setlist is a draft
   * @default false
   */
  isDraft?: boolean;
  /**
   * Whether the setlist is published
   * @default false
   */
  isPublished?: boolean;
}

export interface SetlistApiPostSetlistData {
  ocs: {
    meta: OCSMeta;
    data: Setlist;
  };
}

export interface SetlistApiGetSetlistData {
  ocs: {
    meta: OCSMeta;
    data: Setlist;
  };
}

export interface SetlistApiPatchSetlistPayload {
  /**
   * The title of the setlist
   * @default null
   */
  title?: string | null;
  /**
   * The description of the setlist
   * @default null
   */
  description?: string | null;
  /**
   * The start date and time (ISO 8601 format)
   * @default null
   */
  startDateTime?: string | null;
  /**
   * The duration in seconds
   * @format int64
   * @default null
   */
  duration?: number | null;
  /**
   * The default moderation duration in seconds
   * @format int64
   * @default null
   */
  defaultModerationDuration?: number | null;
  /**
   * The ID of the folder collection version
   * @format int64
   * @default null
   */
  folderCollectionVersionId?: number | null;
  /**
   * Whether the setlist is a draft
   * @default null
   */
  isDraft?: boolean | null;
  /**
   * Whether the setlist is published
   * @default null
   */
  isPublished?: boolean | null;
}

export interface SetlistApiPatchSetlistData {
  ocs: {
    meta: OCSMeta;
    data: Setlist;
  };
}

export interface SetlistApiDeleteSetlistData {
  ocs: {
    meta: OCSMeta;
    data: object;
  };
}

export interface SetlistApiPostCloneSetlistPayload {
  /** The title for the cloned setlist */
  title: string;
}

export interface SetlistApiPostCloneSetlistData {
  ocs: {
    meta: OCSMeta;
    data: Setlist;
  };
}

export interface SetlistApiGetSetlistEntriesData {
  ocs: {
    meta: OCSMeta;
    data: SetlistEntry[];
  };
}

export interface SetlistApiPostSetlistEntryPayload {
  /**
   * The index position for the entry
   * @format int64
   */
  index: number;
  /**
   * Optional comment for the entry
   * @default null
   */
  comment?: string | null;
  /**
   * Moderation duration in seconds
   * @format int64
   * @default null
   */
  moderationDuration?: number | null;
  /**
   * Break duration in seconds (for break entries)
   * @format int64
   * @default null
   */
  breakDuration?: number | null;
  /**
   * The ID of the score (for score entries)
   * @format int64
   * @default null
   */
  scoreId?: number | null;
}

export interface SetlistApiPostSetlistEntryData {
  ocs: {
    meta: OCSMeta;
    data: SetlistEntry;
  };
}

export interface SetlistEntryApiGetSetlistEntryData {
  ocs: {
    meta: OCSMeta;
    data: SetlistEntry;
  };
}

export interface SetlistEntryApiPatchSetlistEntryPayload {
  /**
   * The new index position
   * @format int64
   * @default null
   */
  index?: number | null;
  /**
   * Optional comment for the entry
   * @default null
   */
  comment?: string | null;
  /**
   * Moderation duration in seconds
   * @format int64
   * @default null
   */
  moderationDuration?: number | null;
  /**
   * Break duration in seconds
   * @format int64
   * @default null
   */
  breakDuration?: number | null;
  /**
   * The ID of the score
   * @format int64
   * @default null
   */
  scoreId?: number | null;
}

export interface SetlistEntryApiPatchSetlistEntryData {
  ocs: {
    meta: OCSMeta;
    data: SetlistEntry;
  };
}

export interface SetlistEntryApiDeleteSetlistEntryData {
  ocs: {
    meta: OCSMeta;
    data: object;
  };
}

export interface SetlistEntryApiPostSetlistEntriesBatchPayload {
  /** Array of entry data with IDs */
  entries: {
    /** @format int64 */
    id: number;
    /** @format int64 */
    index?: number;
    comment?: string | null;
    /** @format int64 */
    moderationDuration?: number | null;
    /** @format int64 */
    breakDuration?: number | null;
    /** @format int64 */
    scoreId?: number | null;
  }[];
}

export interface SetlistEntryApiPostSetlistEntriesBatchData {
  ocs: {
    meta: OCSMeta;
    data: SetlistEntry[];
  };
}

export interface TagApiGetTagsData {
  ocs: {
    meta: OCSMeta;
    data: Tag[];
  };
}

export interface TagApiPostTagPayload {
  /** The name of the new tag */
  name: string;
}

export interface TagApiPostTagData {
  ocs: {
    meta: OCSMeta;
    data: Tag;
  };
}
