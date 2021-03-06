<?php
$db = DevblocksPlatform::getDatabaseService();
$logger = DevblocksPlatform::getConsoleLog();
$tables = $db->metaTables();

// ===========================================================================
// Add context owners to snippets

if(!isset($tables['snippet'])) {
	$logger->error("The 'snippet' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('snippet');

if(!isset($columns['owner_context']) && !isset($columns['owner_context_id'])) {
	$db->ExecuteMaster(sprintf(
		"ALTER TABLE snippet ".
		"ADD COLUMN owner_context VARCHAR(128) NOT NULL DEFAULT '', ".
		"ADD COLUMN owner_context_id INT NOT NULL DEFAULT 0, ".
		"ADD INDEX owner_compound (owner_context, owner_context_id), ".
		"ADD INDEX owner_context (owner_context) "
	));
}

if(isset($columns['created_by'])) {
	$db->ExecuteMaster(sprintf("UPDATE snippet SET owner_context='cerberusweb.contexts.worker', owner_context_id=created_by WHERE created_by > 0"));
	$db->ExecuteMaster("ALTER TABLE snippet DROP COLUMN created_by");
}

if(isset($columns['last_updated'])) {
	$db->ExecuteMaster("ALTER TABLE snippet DROP COLUMN last_updated");
}

if(isset($columns['last_updated_by'])) {
	$db->ExecuteMaster("ALTER TABLE snippet DROP COLUMN last_updated_by");
}
 
if(isset($columns['is_private'])) {
	$db->ExecuteMaster("ALTER TABLE snippet DROP COLUMN is_private");
}

// ===========================================================================
// Worker roles refactor

if(!isset($tables['worker_role'])) {
 	$logger->error("The 'worker_role' table does not exist.");
 	return FALSE;
}

list($columns, $indexes) = $db->metaTable('worker_role');

if(!isset($columns['params_json'])) {
	$db->ExecuteMaster("ALTER TABLE worker_role ADD COLUMN params_json TEXT");
	
	// Map workers to roles
	$role_to_workers = array();
	$results = $db->GetArrayMaster("SELECT worker_id, role_id FROM worker_to_role");
	foreach($results as $row) {
		$role_id = $row['role_id'];
		$worker_id = $row['worker_id'];
		
		if(!isset($role_to_workers))
			$role_to_workers[$role_id] = array();
		
		$role_to_workers[$role_id][] = intval($worker_id);
	}
	
	$results = $db->GetArrayMaster("SELECT id FROM worker_role");

	foreach($results as $row) {
		$role_id = $row['id'];
		
		$who_list = isset($role_to_workers[$role_id]) ? $role_to_workers[$role_id] : array();
		
		$db->ExecuteMaster(sprintf("UPDATE worker_role SET params_json = %s WHERE id = %d",
			$db->qstr(json_encode(array(
				'who' => 'workers',
				'who_list' => $who_list,
				'what' => 'itemized',
			))),
			$role_id
		));
	}
	
	unset($results);
	unset($role_to_workers);
	
	$db->ExecuteMaster("DROP TABLE worker_to_role");
}

$acl_enabled = $db->GetOneMaster("SELECT value FROM devblocks_setting WHERE setting = 'acl_enabled'");

if(!is_null($acl_enabled)) {
	// If ACL was disabled, add a default role for everyone that can do everything
	if(!$acl_enabled) {
		$db->ExecuteMaster(sprintf("INSERT INTO worker_role (name,params_json) ".
			"VALUES ('Default',%s)",
			$db->qstr(json_encode(array(
				'who' => 'all',
				'what' => 'all',
			)))
		));
	} else {
		// Otherwise, if ACL was enabled, add default privs for mail + activity
		$results = $db->GetArrayMaster("SELECT id FROM worker_role");
		
		foreach($results as $row) {
			$db->ExecuteMaster(sprintf("INSERT INTO worker_role_acl (role_id, priv_id) VALUES (%d, %s)",
				$row['id'],
				$db->qstr('core.mail')
			));
			$db->ExecuteMaster(sprintf("INSERT INTO worker_role_acl (role_id, priv_id) VALUES (%d, %s)",
				$row['id'],
				$db->qstr('core.activity')
			));
		}
	}
	
	$db->ExecuteMaster("DELETE FROM devblocks_setting WHERE setting = 'acl_enabled'");
}

// ===========================================================================
// Worker ACL refactor

if(!isset($tables['worker_role_acl'])) {
 	$logger->error("The 'worker_role_acl' table does not exist.");
 	return FALSE;
}

list($columns, $indexes) = $db->metaTable('worker_role_acl');

if(isset($columns['has_priv'])) {
	$db->ExecuteMaster("ALTER TABLE worker_role_acl DROP COLUMN has_priv");
}

// ===========================================================================
// Workspace ownership refactor

if(!isset($tables['workspace'])) {
 	$logger->error("The 'workspace' table does not exist.");
 	return FALSE;
}

list($columns, $indexes) = $db->metaTable('workspace');

if(!isset($columns['owner_context'])) {
	$db->ExecuteMaster("ALTER TABLE workspace ADD COLUMN owner_context VARCHAR(255) DEFAULT '' NOT NULL, ADD INDEX owner_context (owner_context)");
}

if(!isset($columns['owner_context_id'])) {
	$db->ExecuteMaster("ALTER TABLE workspace ADD COLUMN owner_context_id INT UNSIGNED DEFAULT 0 NOT NULL, ADD INDEX owner_context_id (owner_context_id)");
}

if(isset($columns['worker_id'])) {
	$db->ExecuteMaster("UPDATE workspace SET owner_context = 'cerberusweb.contexts.worker', owner_context_id = worker_id WHERE owner_context = '' AND owner_context_id = 0");
	$db->ExecuteMaster("ALTER TABLE workspace DROP COLUMN worker_id");
}

// ===========================================================================
// Workspace endpoint refactor

if(!isset($tables['workspace_to_endpoint'])) {
 	$logger->error("The 'workspace_to_endpoint' table does not exist.");
 	return FALSE;
}

list($columns, $indexes) = $db->metaTable('workspace_to_endpoint');

if(!isset($columns['worker_id'])) {
	$db->ExecuteMaster("ALTER TABLE workspace_to_endpoint ADD COLUMN worker_id INT UNSIGNED DEFAULT 0 NOT NULL, ADD INDEX worker_id (worker_id)");
	$db->ExecuteMaster("UPDATE workspace_to_endpoint INNER JOIN workspace ON (workspace_to_endpoint.workspace_id=workspace.id) SET workspace_to_endpoint.worker_id=workspace.owner_context_id WHERE workspace.owner_context = 'cerberusweb.contexts.worker' AND workspace_to_endpoint.worker_id=0");
	$db->ExecuteMaster("DELETE FROM workspace_to_endpoint WHERE worker_id = 0");
}

// If the primary key is compounding 2 fields instead of 3
$diff = array_diff(array('workspace_id','endpoint'), array_keys($indexes['PRIMARY']['columns']));
if(empty($diff)) {
	$db->ExecuteMaster('ALTER TABLE workspace_to_endpoint DROP PRIMARY KEY');
	$db->ExecuteMaster('ALTER TABLE workspace_to_endpoint ADD PRIMARY KEY (workspace_id, worker_id, endpoint)');
}

// ===========================================================================
// Workspace list refactor

if(!isset($tables['workspace_list'])) {
 	$logger->error("The 'workspace_list' table does not exist.");
 	return FALSE;
}

list($columns, $indexes) = $db->metaTable('workspace_list');

if(isset($columns['worker_id'])) {
	$db->ExecuteMaster("ALTER TABLE workspace_list DROP COLUMN worker_id");
}

// ===========================================================================
// Ticket table refactor

if(!isset($tables['ticket'])) {
 	$logger->error("The 'ticket' table does not exist.");
 	return FALSE;
}

list($columns, $indexes) = $db->metaTable('ticket');

$diffs = array();
$do_migrate_orgs = false;

if(isset($columns['team_id']))
	$diffs[] = "CHANGE COLUMN team_id group_id INT UNSIGNED NOT NULL DEFAULT 0";

if(isset($columns['category_id']))
	$diffs[] = "CHANGE COLUMN category_id bucket_id INT UNSIGNED NOT NULL DEFAULT 0";

if(!isset($columns['org_id'])) {
	$do_migrate_orgs = true;
	$diffs[] = "ADD COLUMN org_id INT UNSIGNED NOT NULL DEFAULT 0";
	$diffs[] = "ADD INDEX org_id (org_id)";
}

if(!empty($diffs)) {
	$db->ExecuteMaster(sprintf("ALTER TABLE ticket %s",
		implode(',', $diffs)
	));
	
	if($do_migrate_orgs) {
		$db->ExecuteMaster("UPDATE ticket INNER JOIN address ON (ticket.first_wrote_address_id=address.id AND address.contact_org_id > 0) SET ticket.org_id=address.contact_org_id");
	}
}

// ===========================================================================
// team -> worker_group table refactor

if(isset($tables['team']) && !isset($tables['worker_group'])) {
	$db->ExecuteMaster("RENAME TABLE team TO worker_group");
	unset($tables['team']);
	$tables['worker_group'] = 'worker_group';
}

// ===========================================================================
// category -> bucket table refactor

if(isset($tables['category']) && !isset($tables['bucket'])) {
	$db->ExecuteMaster("RENAME TABLE category TO bucket");
	unset($tables['category']);
	$tables['bucket'] = 'bucket';
}

list($columns, $indexes) = $db->metaTable('bucket');

if(isset($columns['team_id'])) {
	$db->ExecuteMaster("ALTER TABLE bucket CHANGE COLUMN team_id group_id int unsigned not null default 0");
}

// ===========================================================================
// worker_to_team -> worker_to_group table refactor

if(isset($tables['worker_to_team']) && !isset($tables['worker_to_group'])) {
	$db->ExecuteMaster("RENAME TABLE worker_to_team TO worker_to_group");
	unset($tables['worker_to_team']);
	$tables['worker_to_group'] = 'worker_to_group';
}

list($columns, $indexes) = $db->metaTable('worker_to_group');

if(isset($columns['agent_id']))
	$db->ExecuteMaster("ALTER TABLE worker_to_group CHANGE COLUMN agent_id worker_id int unsigned not null default 0");

if(isset($columns['team_id']))
	$db->ExecuteMaster("ALTER TABLE worker_to_group CHANGE COLUMN team_id group_id int unsigned not null default 0");

// ===========================================================================
// Update references in worker_view_model

$replacements = array(
	't_team_id' => 't_group_id',
	't_category_id' => 't_bucket_id',
);

foreach($replacements as $replace_from => $replace_to) {
	$db->ExecuteMaster(sprintf("UPDATE worker_view_model ".
		"SET columns_json=REPLACE(columns_json,'\"%1\$s\"','\"%2\$s\"'), columns_hidden_json=REPLACE(columns_hidden_json,'\"%1\$s\"','\"%2\$s\"'), params_editable_json=REPLACE(params_editable_json,'\"%1\$s\"','\"%2\$s\"'), params_default_json=REPLACE(params_default_json,'\"%1\$s\"','\"%2\$s\"'), params_required_json=REPLACE(params_required_json,'\"%1\$s\"','\"%2\$s\"'), params_hidden_json=REPLACE(params_hidden_json,'\"%1\$s\"','\"%2\$s\"') ".
		"WHERE class_name IN ('View_Ticket','View_Message')",
		$replace_from,
		$replace_to
	));
}

// ===========================================================================
// Update references in workspace_list

$db->ExecuteMaster("UPDATE workspace_list SET list_view=REPLACE(list_view,';s:9:\"t_team_id\"',';s:10:\"t_group_id\"') WHERE context = 'cerberusweb.contexts.ticket'");
$db->ExecuteMaster("UPDATE workspace_list SET list_view=REPLACE(list_view,';s:13:\"t_category_id\"',';s:11:\"t_bucket_id\"') WHERE context = 'cerberusweb.contexts.ticket'");

// ===========================================================================
// Pref cleanup

$db->ExecuteMaster("DELETE FROM worker_pref WHERE setting = 'mail_status_create'");

// ===========================================================================
// Modify VA actions to combine move_to_group and move_to_bucket into a single move_to action

$results = $db->GetArrayMaster("SELECT decision_node.id, decision_node.params_json, trigger_event.owner_context, trigger_event.owner_context_id ".
	"FROM decision_node ".
	"INNER JOIN trigger_event ON (decision_node.trigger_id=trigger_event.id) ".
	"WHERE node_type = 'action' AND params_json LIKE '%move\\_to\\_%'"
);

if(is_array($results) && !empty($results)) {
	foreach($results as $row) {
		$id = $row['id'];
		$owner_context = $row['owner_context'];
		$owner_context_id = $row['owner_context_id'];
		@$params = json_decode($row['params_json'], true);
		
		if(!is_array($params))
			continue;
		
		if(!isset($params['actions']) || !is_array($params['actions']))
			continue;

		$is_move_group = false;
		$is_move_bucket = false;
		
		// Pre-scan
		foreach($params['actions'] as $k => $param) {
			switch($param['action']) {
				case 'move_to_group':
					$is_move_group = true;
					break;
				case 'move_to_bucket':
					$is_move_bucket = true;
					break;
			}
		}
		
		// Changes
		foreach($params['actions'] as $k => $param) {
			switch($param['action']) {
				case 'move_to_group':
					$group_id = intval($param['group_id']);
					
					if(!empty($group_id)) {
						$params['actions'][$k] = array(
							'action' => 'move_to',
							'group_id' => $group_id,
							'bucket_id' => 0,
						);
					} else {
						unset($params['actions'][$k]);
					}
					
					break;
					
				case 'move_to_bucket':
					// If we're already moving groups, favor that action instead.
					if($is_move_group) {
						unset($params['actions'][$k]);
						break;
					}
					
					$group_id = 0;
					$bucket_id = intval($param['bucket_id']);
					
					switch($owner_context) {
						case CerberusContexts::CONTEXT_GROUP:
							$group_id = intval($owner_context_id);
							break;
						default:
							if(!empty($bucket_id)) {
								$group_id = intval($db->GetOneMaster(sprintf("SELECT group_id FROM bucket WHERE id = %d", $bucket_id)));
							}
							break;
					}
					
					if(!empty($group_id)) {
						$params['actions'][$k] = array(
							'action' => 'move_to',
							'group_id' => $group_id,
							'bucket_id' => $bucket_id,
						);
					} else {
						unset($params['actions'][$k]);
					}
					
					break;
			}
		}
		
		$db->ExecuteMaster(sprintf("UPDATE decision_node SET params_json = %s WHERE id = %d",
			$db->qstr(json_encode($params)),
			$id
		));
	}
}

unset($results);

// ===========================================================================
// Nuke abandoned worker_view_model rows

$results = $db->GetArrayMaster("SELECT DISTINCT worker_view_model.view_id, workspace_list.id ".
	"FROM worker_view_model ".
	"LEFT JOIN workspace_list ON (worker_view_model.view_id=concat('cust_',workspace_list.id)) ".
	"HAVING worker_view_model.view_id LIKE 'cust_%' ".
	"AND workspace_list.id IS NULL"
);

if(is_array($results))
foreach($results as $row) {
	$db->ExecuteMaster(sprintf("DELETE FROM worker_view_model WHERE view_id = %s",
		$db->qstr($row['view_id'])
	));
}

return TRUE;

