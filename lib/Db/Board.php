<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

/**
 * @method int getId()
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method int getShared()
 * @method void setShared(int $shared)
 * @method bool isArchived()
 * @method bool getArchived()
 * @method void setArchived(bool $archived)
 * @method int getDeletedAt()
 * @method void setDeletedAt(int $deletedAt)
 * @method int getLastModified()
 * @method void setLastModified(int $lastModified)
 * @method string getOwner()
 * @method void setOwner(string $owner)
 * @method string getColor()
 * @method void setColor(string $color)
 * @method void setShareToken(string $shareToken)
 * @method string getShareToken()
 * @method void setExternalId(int $externalId)
 * @method int | null getExternalId()
 */
class Board extends RelationalEntity {
	protected $title;
	protected $owner;
	protected $color;
	protected $archived = false;
	/** @var Label[]|null */
	protected $labels = null;
	/** @var Acl[]|null */
	protected $acl = null;
	protected $permissions = [];
	protected $users = [];
	protected $shared;
	protected $stacks = [];
	protected $activeSessions = [];
	protected $deletedAt = 0;
	protected $lastModified = 0;
	protected $shareToken = null;
	protected $externalId = null;

	protected $settings = [];

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('shared', 'integer');
		$this->addType('archived', 'boolean');
		$this->addType('deletedAt', 'integer');
		$this->addType('lastModified', 'integer');
		$this->addType('shareToken', 'string');
		$this->addType('externalId', 'integer');
		$this->addRelation('labels');
		$this->addRelation('acl');
		$this->addRelation('shared');
		$this->addRelation('users');
		$this->addRelation('activeSessions');
		$this->addRelation('permissions');
		$this->addRelation('stacks');
		$this->addRelation('settings');
		$this->addResolvable('owner');
		$this->shared = -1;
	}

	public function jsonSerialize(): array {
		$json = parent::jsonSerialize();
		if ($this->shared === -1) {
			unset($json['shared']);
		}
		// FIXME: Ideally the API responses should follow the internal data structure and return null if the labels/acls have not been fetched from the db
		// however this would be a breaking change for consumers of the API
		$json['acl'] = $this->acl ?? [];
		$json['labels'] = $this->labels ?? [];

		$row = false;
		try {
			$db = \OCP\Server::get(\OCP\IDBConnection::class);
			$qb = $db->getQueryBuilder();
			$qb->select('p.type')
				->from('custom_projects', 'p')
				->where($qb->expr()->eq('p.board_id', $qb->createNamedParameter((string)$this->getId())));
			$res = $qb->executeQuery();
			$row = $res->fetch();
			$res->closeCursor();
		} catch (\Throwable $e) {
			$row = false;
		}

		$policySettings = false;
		if ($row !== false) {
			try {
				$qb = $db->getQueryBuilder();
				$qb->select('done_stack_id', 'approved_stack_id', 'policy_version')
					->from('pc_board_policy_settings')
					->where($qb->expr()->eq('board_id', $qb->createNamedParameter((int)$this->getId(), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
				$res = $qb->executeQuery();
				$policySettings = $res->fetch();
				$res->closeCursor();
			} catch (\Throwable $e) {
				$policySettings = false;
			}
		}
		$policySettings = is_array($policySettings) ? $policySettings : [];
		$doneStackId = $policySettings['done_stack_id'] ?? null;
		$approvedStackId = $policySettings['approved_stack_id'] ?? null;
		$json['isProjectBoard'] = !empty($row);
		$json['projectType'] = isset($row['type']) ? (int)$row['type'] : null;
		$json['completionByStack'] = isset($row['type']) && $doneStackId !== null && (int)$row['type'] === 0;
		$json['doneStackId'] = $doneStackId !== null ? (int)$doneStackId : null;
		$json['approvedStackId'] = $approvedStackId !== null ? (int)$approvedStackId : null;
		$json['policyVersion'] = isset($policySettings['policy_version']) ? (int)$policySettings['policy_version'] : 1;

		return $json;
	}

	/**
	 * @param Label[] $labels
	 */
	public function setLabels($labels) {
		$this->labels = $labels;
	}

	/**
	 * @param Acl[] $acl
	 */
	public function setAcl($acl) {
		$this->acl = $acl;
	}

	public function getETag() {
		return md5((string)$this->getLastModified());
	}

	/** @returns Acl[]|null */
	public function getAcl(): ?array {
		return $this->acl;
	}

	/** @returns Label[]|null */
	public function getLabels(): ?array {
		return $this->labels;
	}
}
