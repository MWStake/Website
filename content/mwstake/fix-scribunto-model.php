<?php
require_once __DIR__ . "/Maintenance.php";

/**
 * Force every page in the Module namespace (828) to use the Scribunto
 * content model. Production's XML export serializes modules as "wikitext",
 * so after importDump they must be re-tagged or Scribunto rejects them with
 * "No such module".
 */
class FixScribuntoModel extends Maintenance {
    public function execute() {
        $dbw = MediaWiki\MediaWikiServices::getInstance()
            ->getDBLoadBalancer()->getConnection( DB_PRIMARY );

        // Flip the current revision's slot content model to Scribunto (id 5)
        // for any Module-namespace page still stored as wikitext (id 1).
        $dbw->query(
            "UPDATE content c
               JOIN slots s ON s.slot_content_id = c.content_id
               JOIN page p ON p.page_latest = s.slot_revision_id AND p.page_namespace = 828
               SET c.content_model = 5
             WHERE s.slot_role_id = 1 AND c.content_model = 1",
            __METHOD__
        );

        // Keep the page-level content model in sync (NULL falls back to the
        // namespace default, which is Scribunto, but be explicit).
        $dbw->query(
            "UPDATE page p
               SET p.page_content_model = 'Scribunto'
             WHERE p.page_namespace = 828
               AND ( p.page_content_model IS NULL OR p.page_content_model <> 'Scribunto' )",
            __METHOD__
        );

        $this->output( "Module content models set to Scribunto.\n" );
    }
}
$maintClass = "FixScribuntoModel";
require_once RUN_MAINTENANCE_IF_MAIN;
