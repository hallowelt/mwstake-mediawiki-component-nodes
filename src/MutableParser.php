<?php

namespace MWStake\MediaWiki\Lib\Nodes;

use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use User;

abstract class MutableParser implements IMutator {
	/** @var RevisionRecord */
	protected $revision;
	/** @var mixed */
	protected $rawData = '';
	/** @var bool */
	private $mutated;

	/**
	 * @param RevisionRecord $revision
	 */
	public function __construct(
		RevisionRecord $revision
	) {
		$this->revision = $revision;
		$this->mutated = $revision instanceof MutableRevisionRecord;
	}

	/**
	 * @param string $raw
	 */
	protected function setRawData( $raw ) {
		$this->rawData = $raw;
	}

	/**
	 * @return RevisionRecord
	 */
	public function getRevision(): RevisionRecord {
		return $this->revision;
	}

	/**
	 * @inheritDoc
	 */
	public function getMutatedData(): ?string {
		if ( !$this->mutated ) {
			return null;
		}
		return $this->rawData;
	}

	/**
	 * @param User|null $user
	 * @param string $comment
	 * @param int $flags
	 * @return RevisionRecord|null
	 * @throws \MWException
	 */
	public function saveRevision(
		$user = null, $comment = '', $flags = 0
	): ?RevisionRecord {
		if ( !$this->mutated ) {
			return null;
		}
		$title = $this->revision->getPageAsLinkTarget();
		$wikipage = \WikiPage::factory( $title );

		if ( !$user ) {
			$user = \User::newSystemUser( 'Mediawiki default' );
		}
		$updater = $wikipage->newPageUpdater( $user );
		$updater->setContent( SlotRecord::MAIN, $this->revision->getContent( SlotRecord::MAIN ) );
		$rev = $updater->saveRevision( \CommentStoreComment::newUnsavedComment( $comment ), $flags );
		if ( $rev ) {
			$this->mutated = false;
			$this->revision = $rev;
			return $rev;
		}

		return null;
	}

	/**
	 * @inheritDoc
	 */
	abstract public function addNode( INode $node, $mode = 'append', $newline = true ): void;

	/**
	 * @param IMutableNode $node
	 * @return bool
	 */
	abstract public function replaceNode( IMutableNode $node ): bool;

	/**
	 * @param INode $node
	 * @return bool
	 */
	abstract public function removeNode( INode $node ): bool;

	/**
	 * @return \Content
	 */
	abstract protected function getContentObject(): \Content;

	protected function isMutated(): bool {
		return $this->mutated;
	}

	/**
	 * @throws \MWException
	 */
	protected function setRevisionContent() {
		$content = $this->getContentObject();
		if ( !( $this->revision instanceof MutableRevisionRecord ) ) {
			$this->revision = new MutableRevisionRecord( $this->revision->getPageAsLinkTarget() );
		}
		$this->revision->setSlot( SlotRecord::newUnsaved(
			SlotRecord::MAIN,
			$content
		) );
		$this->mutated = true;
	}
}
