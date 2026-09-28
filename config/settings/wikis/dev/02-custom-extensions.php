<?php
// Custom extensions copied from mwstake.org that are not in the Canasta image.
// CopyWatchers and MagicNumberedHeadings are intentionally omitted because they
// break the source wiki (commented out in LocalSettings.php).
require_once "$IP/extensions/CustomNavBlocks/CustomNavBlocks.php";
$wgCustomNavBlocksEnable = true;
wfLoadExtension( 'HierarchyBuilder' );
wfLoadExtension( 'JSBreadCrumbs' );
wfLoadExtension( 'ModernTimeline' );
wfLoadExtension( 'PipeEscape' );
wfLoadExtension( 'SemanticActions' );
$egSemanticActionsAssigneeValuesFrom = "User";
wfLoadExtension( 'SemanticRating' );
wfLoadExtension( 'TitleIcon' );
