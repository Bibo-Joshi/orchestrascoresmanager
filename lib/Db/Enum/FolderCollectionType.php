<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db\Enum;

enum FolderCollectionType: string {
	case ALPHABETICAL = 'alphabetical';
	case INDEXED = 'indexed';
}
