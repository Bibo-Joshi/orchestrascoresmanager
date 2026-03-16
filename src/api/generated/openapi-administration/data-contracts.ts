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

export interface OCSMeta {
  status: string;
  statuscode: number;
  message?: string;
  totalitems?: string;
  itemsperpage?: string;
}

export interface AdminGetEditGroupsData {
  ocs: {
    meta: OCSMeta;
    data: {
      editGroups: string[];
    };
  };
}

export interface AdminPostEditGroupsPayload {
  /**
   * List of group IDs allowed to edit
   * @default []
   */
  editGroups?: string[];
}

export interface AdminPostEditGroupsData {
  ocs: {
    meta: OCSMeta;
    data: {
      editGroups: string[];
    };
  };
}
