<?php
// Configuration copied from mwstake.org LocalSetttings.php (non-sensitive settings).

// CirrusSearch
$wgSearchType = 'CirrusSearch';
$wgCirrusSearchServers = [ 'elasticsearch' ];

// DisplayTitle
$wgAllowDisplayTitle = true;
$wgRestrictDisplayTitle = false;
$wgDisplayTitleHideSubtitle = true;

// HeaderTabs
$htRenderSingleTab = true;
$htEditTabLink = false;

// ParserFunctions
$wgPFEnableStringFunctions = true;

// Scribunto
$wgScribuntoUseGeSHi = true;
$wgScribuntoUseCodeEditor = true;

// WikiEditor
$wgDefaultUserOptions['usebetatoolbar'] = 1;
$wgDefaultUserOptions['usebetatoolbar-cgd'] = 1;
$wgDefaultUserOptions['wikieditor-preview'] = 1;

// PageForms
$sfgRenameEditTabs = true;

// JSBreadCrumbs
$wgDefaultUserOptions['jsbreadcrumbs-showcrumbssidebar'] = true;
$wgDefaultUserOptions['jsbreadcrumbs-horizontal'] = false;

// SemanticMediaWiki
enableSemantics( $wgSitename );
$smwgLinksInValues = true;
$smwgPageSpecialProperties = array( '_MDAT', '_CDAT' );

// SemanticResultFormats
$srfgFormats[] = 'tagcloud';

// SemanticExtraSpecialProperties
$sespSpecialProperties[] = '_EUSER';
$sespSpecialProperties[] = '_CUSER';
$sespSpecialProperties[] = '_VIEWS';
$sespgEnabledPropertyList = [
    '_EUSER',
    '_CUSER',
    '_REVID',
    '_PAGELGTH',
    '_NREV',
    '_NTREV',
    '_SUBP',
    '_USERREG',
    '_USEREDITCNT',
    '_USERRIGHT',
    '_USERGROUP'
];
