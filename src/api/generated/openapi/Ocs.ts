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

import {
  CommentApiDeleteCommentData,
  CommentApiGetCommentData,
  FolderCollectionApiDeleteFolderCollectionData,
  FolderCollectionApiDeleteFolderCollectionScoreBookData,
  FolderCollectionApiDeleteFolderCollectionScoreData,
  FolderCollectionApiGetFolderCollectionData,
  FolderCollectionApiGetFolderCollectionScoreBooksData,
  FolderCollectionApiGetFolderCollectionScoresData,
  FolderCollectionApiGetFolderCollectionsData,
  FolderCollectionApiGetFolderCollectionVersionsData,
  FolderCollectionApiPatchFolderCollectionData,
  FolderCollectionApiPatchFolderCollectionPayload,
  FolderCollectionApiPostFolderCollectionData,
  FolderCollectionApiPostFolderCollectionPayload,
  FolderCollectionApiPostFolderCollectionScoreBookData,
  FolderCollectionApiPostFolderCollectionScoreBookPayload,
  FolderCollectionApiPostFolderCollectionScoreData,
  FolderCollectionApiPostFolderCollectionScorePayload,
  FolderCollectionApiPostFolderCollectionVersionData,
  FolderCollectionApiPostFolderCollectionVersionPayload,
  FolderCollectionApiStartNewVersionData,
  FolderCollectionApiStartNewVersionPayload,
  FolderCollectionVersionApiGetFolderCollectionVersionData,
  FolderCollectionVersionApiPatchFolderCollectionVersionData,
  FolderCollectionVersionApiPatchFolderCollectionVersionPayload,
  OCSMeta,
  ScoreApiDeleteScoreData,
  ScoreApiGetScoreCommentsData,
  ScoreApiGetScoreFolderCollectionsData,
  ScoreApiGetScoresData,
  ScoreApiPatchScoreData,
  ScoreApiPatchScorePayload,
  ScoreApiPostScoreCommentData,
  ScoreApiPostScoreCommentPayload,
  ScoreApiPostScoreData,
  ScoreApiPostScorePayload,
  ScoreBookApiDeleteScoreBookData,
  ScoreBookApiDeleteScoreBookScoreData,
  ScoreBookApiGetScoreBookData,
  ScoreBookApiGetScoreBookFolderCollectionsData,
  ScoreBookApiGetScoreBookScoresData,
  ScoreBookApiGetScoreBooksData,
  ScoreBookApiPatchScoreBookData,
  ScoreBookApiPatchScoreBookPayload,
  ScoreBookApiPostScoreBookData,
  ScoreBookApiPostScoreBookPayload,
  ScoreBookApiPostScoreBookScoreData,
  ScoreBookApiPostScoreBookScorePayload,
  ScoreBookApiPostScoreBookScoresBatchData,
  ScoreBookApiPostScoreBookScoresBatchPayload,
  SetlistApiDeleteSetlistData,
  SetlistApiGetSetlistData,
  SetlistApiGetSetlistEntriesData,
  SetlistApiGetSetlistsData,
  SetlistApiPatchSetlistData,
  SetlistApiPatchSetlistPayload,
  SetlistApiPostCloneSetlistData,
  SetlistApiPostCloneSetlistPayload,
  SetlistApiPostSetlistData,
  SetlistApiPostSetlistEntryData,
  SetlistApiPostSetlistEntryPayload,
  SetlistApiPostSetlistPayload,
  SetlistEntryApiDeleteSetlistEntryData,
  SetlistEntryApiGetSetlistEntryData,
  SetlistEntryApiPatchSetlistEntryData,
  SetlistEntryApiPatchSetlistEntryPayload,
  SetlistEntryApiPostSetlistEntriesBatchData,
  SetlistEntryApiPostSetlistEntriesBatchPayload,
  TagApiGetTagsData,
  TagApiPostTagData,
  TagApiPostTagPayload,
} from "./data-contracts";
import { ContentType, HttpClient, RequestParams } from "./http-client";

export class Ocs<
  SecurityDataType = unknown,
> extends HttpClient<SecurityDataType> {
  /**
   * @description the comment
   *
   * @tags comment_api
   * @name CommentApiGetComment
   * @summary Get a specific comment by ID
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/comments/{id}
   * @secure
   */
  commentApiGetComment = (id: number, params: RequestParams = {}) =>
    this.request<
      CommentApiGetCommentData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/comments/${id}`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description empty response
   *
   * @tags comment_api
   * @name CommentApiDeleteComment
   * @summary Delete a comment
   * @request DELETE:/ocs/v2.php/apps/orchestrascoresmanager/comments/{id}
   * @secure
   */
  commentApiDeleteComment = (id: number, params: RequestParams = {}) =>
    this.request<
      CommentApiDeleteCommentData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/comments/${id}`,
      method: "DELETE",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the list of folder collections
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiGetFolderCollections
   * @summary Return all available folder collections
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections
   * @secure
   */
  folderCollectionApiGetFolderCollections = (params: RequestParams = {}) =>
    this.request<
      FolderCollectionApiGetFolderCollectionsData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the created folder collection
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiPostFolderCollection
   * @summary Create a new folder collection
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections
   * @secure
   */
  folderCollectionApiPostFolderCollection = (
    data: FolderCollectionApiPostFolderCollectionPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiPostFolderCollectionData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description the folder collection
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiGetFolderCollection
   * @summary Get a specific folder collection by ID
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/{id}
   * @secure
   */
  folderCollectionApiGetFolderCollection = (
    id: number,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiGetFolderCollectionData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/${id}`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the updated folder collection
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiPatchFolderCollection
   * @summary Update an existing folder collection
   * @request PATCH:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/{id}
   * @secure
   */
  folderCollectionApiPatchFolderCollection = (
    id: number,
    data?: FolderCollectionApiPatchFolderCollectionPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiPatchFolderCollectionData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/${id}`,
      method: "PATCH",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description empty response
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiDeleteFolderCollection
   * @summary Delete a folder collection
   * @request DELETE:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/{id}
   * @secure
   */
  folderCollectionApiDeleteFolderCollection = (
    id: number,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiDeleteFolderCollectionData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/${id}`,
      method: "DELETE",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the list of scores (with index for indexed collections)
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiGetFolderCollectionScores
   * @summary Get all scores in a specific folder collection (including scores from score books)
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/{id}/scores
   * @secure
   */
  folderCollectionApiGetFolderCollectionScores = (
    id: number,
    query?: {
      /**
       * Optional version ID, uses active version if not specified
       * @format int64
       * @default null
       */
      versionId?: number | null;
    },
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiGetFolderCollectionScoresData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/${id}/scores`,
      method: "GET",
      query: query,
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description Conditions: - The folder collection must exist - The score must exist - If the score is part of a score book, the score book must not be in the collection (cannot add individual scores when their book is in the collection) - For indexed collections: index is required and must not be occupied - For alphabetical collections: index must not be provided empty response
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiPostFolderCollectionScore
   * @summary Add a score to a folder collection
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/{id}/scores
   * @secure
   */
  folderCollectionApiPostFolderCollectionScore = (
    id: number,
    data: FolderCollectionApiPostFolderCollectionScorePayload,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiPostFolderCollectionScoreData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/${id}/scores`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description Conditions: - The score must be directly in the collection (not via a score book) - If the score is in the collection via a score book, the score book must be removed instead empty response
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiDeleteFolderCollectionScore
   * @summary Remove a score from a folder collection
   * @request DELETE:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/{id}/scores/{scoreId}
   * @secure
   */
  folderCollectionApiDeleteFolderCollectionScore = (
    id: number,
    scoreId: number,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiDeleteFolderCollectionScoreData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/${id}/scores/${scoreId}`,
      method: "DELETE",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the list of score books (with index for indexed collections)
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiGetFolderCollectionScoreBooks
   * @summary Get all score books in a folder collection
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/{id}/scorebooks
   * @secure
   */
  folderCollectionApiGetFolderCollectionScoreBooks = (
    id: number,
    query?: {
      /**
       * Optional version ID, uses active version if not specified
       * @format int64
       * @default null
       */
      versionId?: number | null;
    },
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiGetFolderCollectionScoreBooksData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/${id}/scorebooks`,
      method: "GET",
      query: query,
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description Conditions: - The folder collection must exist - The score book must exist - No score from the score book must already be individually in the collection (cannot add book when individual scores from it are in the collection) - For indexed collections: index is required and must not be occupied - For alphabetical collections: index must not be provided empty response
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiPostFolderCollectionScoreBook
   * @summary Add a score book to a folder collection
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/{id}/scorebooks
   * @secure
   */
  folderCollectionApiPostFolderCollectionScoreBook = (
    id: number,
    data: FolderCollectionApiPostFolderCollectionScoreBookPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiPostFolderCollectionScoreBookData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/${id}/scorebooks`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description empty response
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiDeleteFolderCollectionScoreBook
   * @summary Remove a score book from a folder collection
   * @request DELETE:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/{id}/scorebooks/{scoreBookId}
   * @secure
   */
  folderCollectionApiDeleteFolderCollectionScoreBook = (
    id: number,
    scoreBookId: number,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiDeleteFolderCollectionScoreBookData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/${id}/scorebooks/${scoreBookId}`,
      method: "DELETE",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the list of versions
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiGetFolderCollectionVersions
   * @summary Get all versions of a folder collection
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/{id}/versions
   * @secure
   */
  folderCollectionApiGetFolderCollectionVersions = (
    id: number,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiGetFolderCollectionVersionsData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/${id}/versions`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the created version
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiPostFolderCollectionVersion
   * @summary Create a new version for a folder collection
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/{id}/versions
   * @secure
   */
  folderCollectionApiPostFolderCollectionVersion = (
    id: number,
    data: FolderCollectionApiPostFolderCollectionVersionPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiPostFolderCollectionVersionData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/${id}/versions`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description the created version
   *
   * @tags folder_collection_api
   * @name FolderCollectionApiStartNewVersion
   * @summary Start a new version (deactivates current version, creates new one starting on specified date)
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/{id}/versions/new
   * @secure
   */
  folderCollectionApiStartNewVersion = (
    id: number,
    data?: FolderCollectionApiStartNewVersionPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionApiStartNewVersionData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollections/${id}/versions/new`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description the version
   *
   * @tags folder_collection_version_api
   * @name FolderCollectionVersionApiGetFolderCollectionVersion
   * @summary Get a folder collection version by ID
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/foldercollectionversions/{id}
   * @secure
   */
  folderCollectionVersionApiGetFolderCollectionVersion = (
    id: number,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionVersionApiGetFolderCollectionVersionData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollectionversions/${id}`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description Only valid_to can be set for active versions to deactivate them. Non-active versions cannot be modified. the updated version
   *
   * @tags folder_collection_version_api
   * @name FolderCollectionVersionApiPatchFolderCollectionVersion
   * @summary Update a folder collection version
   * @request PATCH:/ocs/v2.php/apps/orchestrascoresmanager/foldercollectionversions/{id}
   * @secure
   */
  folderCollectionVersionApiPatchFolderCollectionVersion = (
    id: number,
    data?: FolderCollectionVersionApiPatchFolderCollectionVersionPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      FolderCollectionVersionApiPatchFolderCollectionVersionData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/foldercollectionversions/${id}`,
      method: "PATCH",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description the list of scores
   *
   * @tags score_api
   * @name ScoreApiGetScores
   * @summary Return all available scores or a list of specific scores by IDs
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/scores
   * @secure
   */
  scoreApiGetScores = (
    query?: {
      /**
       * Optional list of score IDs to fetch
       * @default null
       */
      "ids[]"?: number[] | null;
    },
    params: RequestParams = {},
  ) =>
    this.request<
      ScoreApiGetScoresData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scores`,
      method: "GET",
      query: query,
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the created score
   *
   * @tags score_api
   * @name ScoreApiPostScore
   * @summary Add a new score
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/scores
   * @secure
   */
  scoreApiPostScore = (
    data: ScoreApiPostScorePayload,
    params: RequestParams = {},
  ) =>
    this.request<
      ScoreApiPostScoreData,
      | {
          ocs: {
            meta: OCSMeta;
            data: any;
          };
        }
      | string
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scores`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description the updated score
   *
   * @tags score_api
   * @name ScoreApiPatchScore
   * @summary Update an existing score
   * @request PATCH:/ocs/v2.php/apps/orchestrascoresmanager/scores/{id}
   * @secure
   */
  scoreApiPatchScore = (
    id: number,
    data?: ScoreApiPatchScorePayload,
    params: RequestParams = {},
  ) =>
    this.request<
      ScoreApiPatchScoreData,
      | {
          ocs: {
            meta: OCSMeta;
            data: any;
          };
        }
      | string
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scores/${id}`,
      method: "PATCH",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description empty response
   *
   * @tags score_api
   * @name ScoreApiDeleteScore
   * @summary Delete a specific score
   * @request DELETE:/ocs/v2.php/apps/orchestrascoresmanager/scores/{id}
   * @secure
   */
  scoreApiDeleteScore = (id: number, params: RequestParams = {}) =>
    this.request<
      ScoreApiDeleteScoreData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scores/${id}`,
      method: "DELETE",
      secure: true,
      ...params,
    });
  /**
   * @description the list of comments
   *
   * @tags score_api
   * @name ScoreApiGetScoreComments
   * @summary Get all comments for a specific score
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/scores/{id}/comments
   * @secure
   */
  scoreApiGetScoreComments = (id: number, params: RequestParams = {}) =>
    this.request<
      ScoreApiGetScoreCommentsData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scores/${id}/comments`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the created comment
   *
   * @tags score_api
   * @name ScoreApiPostScoreComment
   * @summary Create a new comment for a specific score
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/scores/{id}/comments
   * @secure
   */
  scoreApiPostScoreComment = (
    id: number,
    data: ScoreApiPostScoreCommentPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      ScoreApiPostScoreCommentData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scores/${id}/comments`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description the list of folder collection information
   *
   * @tags score_api
   * @name ScoreApiGetScoreFolderCollections
   * @summary Get all folder collections for a specific score
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/scores/{id}/foldercollections
   * @secure
   */
  scoreApiGetScoreFolderCollections = (
    id: number,
    params: RequestParams = {},
  ) =>
    this.request<
      ScoreApiGetScoreFolderCollectionsData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scores/${id}/foldercollections`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the list of score books
   *
   * @tags score_book_api
   * @name ScoreBookApiGetScoreBooks
   * @summary Return all available score books
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/scorebooks
   * @secure
   */
  scoreBookApiGetScoreBooks = (params: RequestParams = {}) =>
    this.request<
      ScoreBookApiGetScoreBooksData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scorebooks`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the created score book
   *
   * @tags score_book_api
   * @name ScoreBookApiPostScoreBook
   * @summary Create a new score book
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/scorebooks
   * @secure
   */
  scoreBookApiPostScoreBook = (
    data: ScoreBookApiPostScoreBookPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      ScoreBookApiPostScoreBookData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scorebooks`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description the score book
   *
   * @tags score_book_api
   * @name ScoreBookApiGetScoreBook
   * @summary Get a specific score book by ID
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/{id}
   * @secure
   */
  scoreBookApiGetScoreBook = (id: number, params: RequestParams = {}) =>
    this.request<
      ScoreBookApiGetScoreBookData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/${id}`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the updated score book
   *
   * @tags score_book_api
   * @name ScoreBookApiPatchScoreBook
   * @summary Update an existing score book
   * @request PATCH:/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/{id}
   * @secure
   */
  scoreBookApiPatchScoreBook = (
    id: number,
    data?: ScoreBookApiPatchScoreBookPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      ScoreBookApiPatchScoreBookData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/${id}`,
      method: "PATCH",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description empty response
   *
   * @tags score_book_api
   * @name ScoreBookApiDeleteScoreBook
   * @summary Delete a score book
   * @request DELETE:/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/{id}
   * @secure
   */
  scoreBookApiDeleteScoreBook = (id: number, params: RequestParams = {}) =>
    this.request<
      ScoreBookApiDeleteScoreBookData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/${id}`,
      method: "DELETE",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the list of scores
   *
   * @tags score_book_api
   * @name ScoreBookApiGetScoreBookScores
   * @summary Get all scores in a score book
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/{id}/scores
   * @secure
   */
  scoreBookApiGetScoreBookScores = (id: number, params: RequestParams = {}) =>
    this.request<
      ScoreBookApiGetScoreBookScoresData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/${id}/scores`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description Conditions: - The score book must exist - The score must exist - The score must not already be part of any score book (a score can only belong to one book) - The index position must not already be occupied in the book empty response
   *
   * @tags score_book_api
   * @name ScoreBookApiPostScoreBookScore
   * @summary Add a score to a score book
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/{id}/scores
   * @secure
   */
  scoreBookApiPostScoreBookScore = (
    id: number,
    data: ScoreBookApiPostScoreBookScorePayload,
    params: RequestParams = {},
  ) =>
    this.request<
      ScoreBookApiPostScoreBookScoreData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/${id}/scores`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description Conditions (for each score): - The score book must exist - Each score must exist - Each score must not already be part of any score book - Each index position must not already be occupied in the book - Index positions in the request must be unique empty response
   *
   * @tags score_book_api
   * @name ScoreBookApiPostScoreBookScoresBatch
   * @summary Add multiple scores to a score book in one request
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/{id}/scores/batch
   * @secure
   */
  scoreBookApiPostScoreBookScoresBatch = (
    id: number,
    data: ScoreBookApiPostScoreBookScoresBatchPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      ScoreBookApiPostScoreBookScoresBatchData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/${id}/scores/batch`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description Conditions: - The score book must exist - The score must be part of this specific score book empty response
   *
   * @tags score_book_api
   * @name ScoreBookApiDeleteScoreBookScore
   * @summary Remove a score from a score book
   * @request DELETE:/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/{id}/scores/{scoreId}
   * @secure
   */
  scoreBookApiDeleteScoreBookScore = (
    id: number,
    scoreId: number,
    params: RequestParams = {},
  ) =>
    this.request<
      ScoreBookApiDeleteScoreBookScoreData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/${id}/scores/${scoreId}`,
      method: "DELETE",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the list of folder collections
   *
   * @tags score_book_api
   * @name ScoreBookApiGetScoreBookFolderCollections
   * @summary Get folder collections containing this score book
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/{id}/foldercollections
   * @secure
   */
  scoreBookApiGetScoreBookFolderCollections = (
    id: number,
    params: RequestParams = {},
  ) =>
    this.request<
      ScoreBookApiGetScoreBookFolderCollectionsData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/scorebooks/${id}/foldercollections`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the list of setlists
   *
   * @tags setlist_api
   * @name SetlistApiGetSetlists
   * @summary Return all setlists with optional filtering
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/setlists
   * @secure
   */
  setlistApiGetSetlists = (
    query?: {
      /**
       * Filter by date: 'all', 'future', or 'past'
       * @default "all"
       */
      filter?: "all" | "future" | "past";
      /**
       * Filter by draft status
       * @default null
       */
      isDraft?: boolean | null;
      /**
       * Filter by published status
       * @default null
       */
      isPublished?: boolean | null;
    },
    params: RequestParams = {},
  ) =>
    this.request<
      SetlistApiGetSetlistsData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/setlists`,
      method: "GET",
      query: query,
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the created setlist
   *
   * @tags setlist_api
   * @name SetlistApiPostSetlist
   * @summary Create a new setlist
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/setlists
   * @secure
   */
  setlistApiPostSetlist = (
    data: SetlistApiPostSetlistPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      SetlistApiPostSetlistData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/setlists`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description the setlist
   *
   * @tags setlist_api
   * @name SetlistApiGetSetlist
   * @summary Get a specific setlist by ID
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/setlists/{id}
   * @secure
   */
  setlistApiGetSetlist = (id: number, params: RequestParams = {}) =>
    this.request<
      SetlistApiGetSetlistData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/setlists/${id}`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the updated setlist
   *
   * @tags setlist_api
   * @name SetlistApiPatchSetlist
   * @summary Update an existing setlist
   * @request PATCH:/ocs/v2.php/apps/orchestrascoresmanager/setlists/{id}
   * @secure
   */
  setlistApiPatchSetlist = (
    id: number,
    data?: SetlistApiPatchSetlistPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      SetlistApiPatchSetlistData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/setlists/${id}`,
      method: "PATCH",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description empty response
   *
   * @tags setlist_api
   * @name SetlistApiDeleteSetlist
   * @summary Delete a setlist
   * @request DELETE:/ocs/v2.php/apps/orchestrascoresmanager/setlists/{id}
   * @secure
   */
  setlistApiDeleteSetlist = (id: number, params: RequestParams = {}) =>
    this.request<
      SetlistApiDeleteSetlistData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/setlists/${id}`,
      method: "DELETE",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the cloned setlist
   *
   * @tags setlist_api
   * @name SetlistApiPostCloneSetlist
   * @summary Clone an existing setlist with a new title
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/setlists/{id}/clone
   * @secure
   */
  setlistApiPostCloneSetlist = (
    id: number,
    data: SetlistApiPostCloneSetlistPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      SetlistApiPostCloneSetlistData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/setlists/${id}/clone`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description the list of entries
   *
   * @tags setlist_api
   * @name SetlistApiGetSetlistEntries
   * @summary Get all entries in a setlist
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/setlists/{id}/entries
   * @secure
   */
  setlistApiGetSetlistEntries = (id: number, params: RequestParams = {}) =>
    this.request<
      SetlistApiGetSetlistEntriesData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/setlists/${id}/entries`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the created entry
   *
   * @tags setlist_api
   * @name SetlistApiPostSetlistEntry
   * @summary Add a new entry to a setlist
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/setlists/{id}/entries
   * @secure
   */
  setlistApiPostSetlistEntry = (
    id: number,
    data: SetlistApiPostSetlistEntryPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      SetlistApiPostSetlistEntryData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/setlists/${id}/entries`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description the entry
   *
   * @tags setlist_entry_api
   * @name SetlistEntryApiGetSetlistEntry
   * @summary Get a specific setlist entry by ID
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/setlistentries/{id}
   * @secure
   */
  setlistEntryApiGetSetlistEntry = (id: number, params: RequestParams = {}) =>
    this.request<
      SetlistEntryApiGetSetlistEntryData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/setlistentries/${id}`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the updated entry
   *
   * @tags setlist_entry_api
   * @name SetlistEntryApiPatchSetlistEntry
   * @summary Update an existing setlist entry
   * @request PATCH:/ocs/v2.php/apps/orchestrascoresmanager/setlistentries/{id}
   * @secure
   */
  setlistEntryApiPatchSetlistEntry = (
    id: number,
    data?: SetlistEntryApiPatchSetlistEntryPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      SetlistEntryApiPatchSetlistEntryData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/setlistentries/${id}`,
      method: "PATCH",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description empty response
   *
   * @tags setlist_entry_api
   * @name SetlistEntryApiDeleteSetlistEntry
   * @summary Delete a setlist entry
   * @request DELETE:/ocs/v2.php/apps/orchestrascoresmanager/setlistentries/{id}
   * @secure
   */
  setlistEntryApiDeleteSetlistEntry = (
    id: number,
    params: RequestParams = {},
  ) =>
    this.request<
      SetlistEntryApiDeleteSetlistEntryData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/setlistentries/${id}`,
      method: "DELETE",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the updated entries
   *
   * @tags setlist_entry_api
   * @name SetlistEntryApiPostSetlistEntriesBatch
   * @summary Batch update multiple setlist entries
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/setlistentries/batch
   * @secure
   */
  setlistEntryApiPostSetlistEntriesBatch = (
    data: SetlistEntryApiPostSetlistEntriesBatchPayload,
    params: RequestParams = {},
  ) =>
    this.request<
      SetlistEntryApiPostSetlistEntriesBatchData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/setlistentries/batch`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
  /**
   * @description the list of tags
   *
   * @tags tag_api
   * @name TagApiGetTags
   * @summary Return all available tags
   * @request GET:/ocs/v2.php/apps/orchestrascoresmanager/tags
   * @secure
   */
  tagApiGetTags = (params: RequestParams = {}) =>
    this.request<
      TagApiGetTagsData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/tags`,
      method: "GET",
      secure: true,
      format: "json",
      ...params,
    });
  /**
   * @description the created tag
   *
   * @tags tag_api
   * @name TagApiPostTag
   * @summary Create a new tag
   * @request POST:/ocs/v2.php/apps/orchestrascoresmanager/tags
   * @secure
   */
  tagApiPostTag = (data: TagApiPostTagPayload, params: RequestParams = {}) =>
    this.request<
      TagApiPostTagData,
      {
        ocs: {
          meta: OCSMeta;
          data: any;
        };
      }
    >({
      path: `/ocs/v2.php/apps/orchestrascoresmanager/tags`,
      method: "POST",
      body: data,
      secure: true,
      type: ContentType.Json,
      format: "json",
      ...params,
    });
}
