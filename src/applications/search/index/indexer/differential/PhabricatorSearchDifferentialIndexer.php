<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorSearchDifferentialIndexer
  extends PhabricatorSearchDocumentIndexer {

  public static function indexRevision(DifferentialRevision $rev) {
    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($rev->getPHID());
    $doc->setDocumentType('DREV');
    $doc->setDocumentTitle($rev->getTitle());
    $doc->setDocumentCreated($rev->getDateCreated());
    $doc->setDocumentModified($rev->getDateModified());

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $rev->getSummary());
    $doc->addField(
      PhabricatorSearchField::FIELD_TEST_PLAN,
      $rev->getTestPlan());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $rev->getAuthorPHID(),
      'USER',
      $rev->getDateCreated());

    if ($rev->getStatus() != DifferentialRevisionStatus::COMMITTED &&
        $rev->getStatus() != DifferentialRevisionStatus::ABANDONED) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
        $rev->getPHID(),
        'DREV',
        time());
    }

    $comments = id(new DifferentialInlineComment())->loadAllWhere(
      'revisionID = %d AND commentID is not null',
      $rev->getID());

    $touches = array();

    foreach ($comments as $comment) {
      if (strlen($comment->getContent())) {
        // TODO: we should also index inline comments.
        $doc->addField(
          PhabricatorSearchField::FIELD_COMMENT,
          $comment->getContent());
      }

      $author = $comment->getAuthorPHID();
      $touches[$author] = $comment->getDateCreated();
    }

    foreach ($touches as $touch => $time) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_TOUCH,
        $touch,
        'USER',
        $time);
    }

    $rev->loadRelationships();

    foreach ($rev->getReviewers() as $phid) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
        $phid,
        'USER',
        $rev->getDateModified()); // Bogus timestamp.
    }

    $ccphids = $rev->getCCPHIDs();
    $handles = id(new PhabricatorObjectHandleData($ccphids))
      ->loadHandles();

    foreach ($handles as $phid => $handle) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_SUBSCRIBER,
        $phid,
        $handle->getType(),
        $rev->getDateModified()); // Bogus timestamp.
    }

    PhabricatorSearchDocument::reindexAbstractDocument($doc);
  }
}
