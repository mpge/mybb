<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: index.php 2992 2007-04-05 14:43:48Z chris $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->template_sets, "index.php?module=style/templates");

$sid = intval($mybb->input['sid']);

$expand_str = "";
$expand_str2 = "";
$expand_array = array();
if(isset($mybb->input['expand']))
{
	$expand_array = explode("|", $mybb->input['expand']);
	array_map("intval", $expand_array);
	$expand_str = "&amp;expand=".implode("|", $expand_array);
	$expand_str2 = "&expand=".implode("|", $expand_array);
}

if($mybb->input['action'] == "add_set" || $mybb->input['action'] == "add_template" || $mybb->input['action'] == "search_replace" || $mybb->input['action'] == "find_updated" || (!$mybb->input['action'] && !$sid))
{
	$sub_tabs['templates'] = array(
		'title' => $lang->manage_template_sets,
		'link' => "index.php?module=style/templates",
		'description' => $lang->manage_template_sets_desc
	);

	$sub_tabs['add_set'] = array(
		'title' => $lang->add_set,
		'link' => "index.php?module=style/templates&amp;action=add_set"
	);

	$sub_tabs['add_template'] = array(
		'title' => $lang->add_template,
		'link' => "index.php?module=style/templates&amp;action=add_template{$expand_str}"
	);
	
	$sub_tabs['search_replace'] = array(
		'title' => $lang->search_replace,
		'link' => "index.php?module=style/templates&amp;action=search_replace",
		'description' => $lang->search_replace_desc
	);
	
	$sub_tabs['find_updated'] = array(
		'title' => $lang->find_updated,
		'link' => "index.php?module=style/templates&amp;action=find_updated",
		'description' => $lang->find_updated_desc
	);
}
else if(($sid && !$mybb->input['action']) || $mybb->input['action'] == "edit_set" || $mybb->input['action'] == "edit_template")
{
	$sub_tabs['manage_templates'] = array(
		'title' => $lang->manage_templates,
		'link' => "index.php?module=style/templates&amp;sid=".$sid.$expand_str,
		'description' => $lang->manage_templates_desc
	);

	if($sid > 0)
	{
		$sub_tabs['edit_set'] = array(
			'title' => $lang->edit_set,
			'link' => "index.php?module=style/templates&amp;action=edit_set&amp;sid=".$sid.$expand_str,
			'description' => $lang->edit_set_desc
		);
	}

	$sub_tabs['add_template'] = array(
		'title' => $lang->add_template,
		'link' => "index.php?module=style/templates&amp;action=add_template&amp;sid=".$sid.$expand_str,
		'description' => $lang->add_template_desc
	);
}

$template_sets = array();
$template_sets[-1] = $lang->global_templates;

$query = $db->simple_select("templatesets", "*", "", array('order_by' => 'title', 'order_dir' => 'ASC'));
while($template_set = $db->fetch_array($query))
{
	$template_sets[$template_set['sid']] = $template_set['title'];
}

$plugins->run_hooks("admin_style_templates");

if($mybb->input['action'] == "add_set")
{
	$plugins->run_hooks("admin_style_templates_add_set");
	
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_set_title;
		}
		
		if(!$errors)
		{
			$sid = $db->insert_query("templatesets", array('title' => $db->escape_string($mybb->input['title'])));
			
			// Log admin action
			log_admin_action($sid, $mybb->input['title']);
			
			flash_message($lang->success_template_set_saved, 'success');
			admin_redirect("index.php?module=style/templates&sid=".$sid);
		}
	}
	
	$page->add_breadcrumb_item($lang->add_set);
	
	$page->output_header($lang->add_set);
	
	$sub_tabs = array();
	$sub_tabs['add_set'] = array(
		'title' => $lang->add_set,
		'link' => "index.php?module=style/templates&amp;action=add_set",
		'description' => $lang->add_set_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'add_set');
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['title'] = "";
	}
	
	$form = new Form("index.php?module=style/templates&amp;action=add_set", "post", "add_set");
	
	$form_container = new FormContainer($lang->add_set);
	$form_container->output_row($lang->title, "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->end();
	
	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->save);

	$form->output_submit_wrapper($buttons);
	
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "add_template")
{
	$plugins->run_hooks("admin_style_templates_add_template");
	
	if($mybb->request_method == "post")
	{
		if(empty($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}
		else
		{	
			$query = $db->simple_select("templates", "COUNT(tid) as count", "title='".$db->escape_string($mybb->input['title'])."' AND (sid = '-2' OR sid = '{$sid}')");
			if($db->fetch_field($query, "count") > 0)
			{
				$errors[] = $lang->error_already_exists;
			}
		}
		
		if(!isset($template_sets[$sid]))
		{
			$errors[] = $lang->error_invalid_set;
		}
		
		if(!$errors)
		{
			$template_array = array(
				'title' => $db->escape_string($mybb->input['title']),
				'sid' => $sid,
				'template' => $db->escape_string($mybb->input['template']),
				'version' => $db->escape_string($mybb->version_code),
				'status' => '',
				'dateline' => TIME_NOW
			);
						
			$tid = $db->insert_query("templates", $template_array);
			
			$plugins->run_hooks("admin_style_templates_add_template_commit");
			
			// Log admin action
			log_admin_action($tid, $mybb->input['title'], $sid, $template_sets[$sid]);
			
			flash_message($lang->success_template_saved, 'success');
			
			if($mybb->input['continue'])
			{
				admin_redirect("index.php?module=style/templates&action=edit_template&tid=".intval($tid)."&sid=".$sid.$expand_str2);
			}
			else
			{
				admin_redirect("index.php?module=style/templates&sid=".$sid.$expand_str2);
			}
		}
	}
	
	if($errors)
	{
		$template = $mybb->input;
	}
	else
	{
		if(!$sid)
		{
			$sid = -1;
		}
		
		$template['template'] = "";
		$template['sid'] = $sid;
	}
	
	if($mybb->input['sid'])
	{
		$page->add_breadcrumb_item($template_sets[$sid], "index.php?module=style/templates&amp;sid={$sid}{$expand_str}");
	}
	
	$page->extra_header .= '
	<link type="text/css" href="./jscripts/codepress/languages/codepress-mybb.css" rel="stylesheet" id="cp-lang-style" />
	<script type="text/javascript" src="./jscripts/codepress/codepress.js"></script>
	<script type="text/javascript">
		CodePress.language = \'mybb\';
	</script>';
	
	$page->add_breadcrumb_item($lang->add_template);
	
	$page->output_header($lang->add_template);
	
	$sub_tabs = array();
	$sub_tabs['add_template'] = array(
		'title' => $lang->add_template,
		'link' => "index.php?module=style/templates&amp;action=add_template&amp;sid=".$template['sid'].$expand_str,
		'description' => $lang->add_template_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'add_template');
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	
	$form = new Form("index.php?module=style/templates&amp;action=add_template{$expand_str}", "post", "add_template");
	
	$form_container = new FormContainer($lang->add_template);
	$form_container->output_row($lang->template_name, $lang->template_name_desc, $form->generate_text_box('title', $template['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->template_set, $lang->template_set_desc, $form->generate_select_box('sid', $template_sets, $sid), 'sid');
	$form_container->output_row("", "", $form->generate_text_area('template', $template['template'], array('id' => 'template', 'class' => 'codepress php', 'style' => 'width: 100%; height: 500px;')), 'template');
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->save_continue, array('name' => 'continue'));
	$buttons[] = $form->generate_submit_button($lang->save_close, array('name' => 'close'));

	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	echo "<script language=\"Javascript\" type=\"text/javascript\">
	Event.observe('add_template', 'submit', function()
	{
		if($('template_cp')) {
			var area = $('template_cp');
			area.id = 'template';
			area.value = template.getCode();
			area.disabled = false;
		}
	});
</script>";

	$page->output_footer();
}

if($mybb->input['action'] == "edit_set")
{
	$plugins->run_hooks("admin_style_templates_edit_set");
	
	$query = $db->simple_select("templatesets", "*", "sid='{$sid}'");
	$set = $db->fetch_array($query);
	if(!$set)
	{
		flash_message($lang->error_invalid_input, 'error');
		admin_redirect("index.php?module=style/templates");
	}
	$sid = $set['sid'];
	
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_set_title;
		}
		
		if(!$errors)
		{
			$query = $db->update_query("templatesets", array('title' => $db->escape_string($mybb->input['title'])), "sid='{$sid}'");
			
			// Log admin action
			log_admin_action($sid, $set['title']);
			
			flash_message($lang->success_template_set_saved, 'success');
			admin_redirect("index.php?module=style/templates&sid=".$sid.$expand_str2);
		}
	}
	
	if($sid)
	{
		$page->add_breadcrumb_item($template_sets[$sid], "index.php?module=style/templates&amp;sid={$sid}{$expand_str}");
	}
	
	$page->add_breadcrumb_item($lang->edit_set);
	
	$page->output_header($lang->edit_set);
	
	$sub_tabs = array();
	$sub_tabs['edit_set'] = array(
		'title' => $lang->edit_set,
		'link' => "index.php?module=style/templates&amp;action=edit_set&amp;sid=".$sid,
		'description' => $lang->edit_set_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_set');
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$query = $db->simple_select("templatesets", "title", "sid='{$sid}'");
		$mybb->input['title'] = $db->fetch_field($query, "title");
	}
	
	$form = new Form("index.php?module=style/templates&amp;action=edit_set{$expand_str}", "post", "edit_set");
	echo $form->generate_hidden_field("sid", $sid);
	
	$form_container = new FormContainer($lang->edit_set);
	$form_container->output_row($lang->title, "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->end();
	
	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->save);

	$form->output_submit_wrapper($buttons);
	
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit_template")
{
	$plugins->run_hooks("admin_style_templates_edit_template");
	
	if(!$mybb->input['tid'] || !$sid)
	{
		flash_message($lang->error_missing_input, 'error');
		admin_redirect("index.php?module=style/templates");
	}
	
	if($mybb->request_method == "post")
	{
		if(empty($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}
		
		if(!$errors)
		{
			$template_array = array(
				'title' => $db->escape_string($mybb->input['title']),
				'sid' => $sid,
				'template' => $db->escape_string(trim($mybb->input['template'])),
				'version' => $mybb->version_code,
				'status' => '',
				'dateline' => TIME_NOW
			);
			
			$tid = intval($mybb->input['tid']);
			if($mybb->input['sid'] > 0)
			{
				$query = $db->simple_select("templates", "COUNT(tid) as count", "title='".$db->escape_string($mybb->input['title'])."' AND (sid = '-2' OR sid = '{$sid}')");
				if($db->fetch_field($query, "count") == 1)
				{
					$tid = $db->insert_query("templates", $template_array);
				}
				else
				{
					$db->update_query("templates", $template_array, "tid='{$tid}'");
				}
			}
			else
			{
				// Global template set
				$db->update_query("templates", $template_array, "tid='{$tid}'");
			}
			
			$plugins->run_hooks("admin_style_templates_edit_template_commit");
			
			$query = $db->simple_select("templatesets", "title", "sid={$sid}");
			$set = $db->fetch_array($query);
			
			$exploded = explode("_", $template_array['title'], 2);
			$prefix = $exploded[0];
			
			$query = $db->simple_select("templategroups", "gid", "prefix = '".$db->escape_string($prefix)."'");
			$group = $db->fetch_field($query, "gid");
			
			if(!$group)
			{
				$group = "-1";
			}		
			
			// Log admin action
			log_admin_action($tid, $mybb->input['title'], $sid, $set['title']);
			
			flash_message($lang->success_template_saved, 'success');
			
			if($mybb->input['continue'])
			{
				admin_redirect("index.php?module=style/templates&action=edit_template&tid=".intval($mybb->input['tid'])."&sid=".$sid.$expand_str2);
			}
			else
			{
				admin_redirect("index.php?module=style/templates&sid=".$sid.$expand_str2."#group_{$group}");
			}
		}
	}
	
	if($errors)
	{
		$page->output_inline_error($errors);
		$template = $mybb->input;
	}
	else
	{
		$query = $db->simple_select("templates", "title", "tid='".intval($mybb->input['tid'])."' AND (sid='-2' OR sid='{$sid}')", array('order_by' => 'sid', 'order_dir' => 'ASC', 'limit' => 1));
		$title = $db->fetch_field($query, "title");
		
		$query = $db->simple_select("templates", "*", "title='".$db->escape_string($title)."' AND (sid='-2' OR sid='{$sid}')", array('order_by' => 'sid', 'order_dir' => 'DESC', 'limit' => 1));
		$template = $db->fetch_array($query);
	}
	
	$page->extra_header .= '
	<link type="text/css" href="./jscripts/codepress/languages/codepress-mybb.css" rel="stylesheet" id="cp-lang-style" />
	<script type="text/javascript" src="./jscripts/codepress/codepress.js"></script>
	<script type="text/javascript">
		CodePress.language = \'mybb\';
	</script>';
	
	$page->add_breadcrumb_item($template_sets[$sid], "index.php?module=style/templates&amp;sid={$sid}{$expand_str}");
	
	$page->add_breadcrumb_item($lang->edit_template_breadcrumb.$template['title'], "index.php?module=style/templates&amp;sid={$sid}");
	
	$page->output_header($lang->edit_template);
	
	$sub_tabs = array();
	$sub_tabs['edit_template'] = array(
		'title' => $lang->edit_template,
		'link' => "index.php?module=style/templates&amp;action=edit_template&amp;tid=".$mybb->input['tid'].$expand_str,
		'description' => $lang->edit_template_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_template');
	
	$form = new Form("index.php?module=style/templates&amp;action=edit_template{$expand_str}", "post", "edit_template");
	echo $form->generate_hidden_field('tid', $template['tid']);
		
	$form_container = new FormContainer($lang->edit_template_breadcrumb.$template['title']);
	$form_container->output_row($lang->template_name, $lang->template_name_desc, $form->generate_text_box('title', $template['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->template_set, $lang->template_set_desc, $form->generate_select_box('sid', $template_sets, $sid));
	$form_container->output_row("", "", $form->generate_text_area('template', $template['template'], array('id' => 'template', 'class' => 'codepress mybb', 'style' => 'width: 100%; height: 500px;')));
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->save_continue, array('name' => 'continue'));
	$buttons[] = $form->generate_submit_button($lang->save_close, array('name' => 'close'));

	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	echo "<script language=\"Javascript\" type=\"text/javascript\">
	Event.observe('edit_template', 'submit', function()
	{
		if($('template_cp')) {
			var area = $('template_cp');
			area.id = 'template';
			area.value = template.getCode();
			area.disabled = false;
		}
	});
</script>";

	$page->output_footer();
}

if($mybb->input['action'] == "search_replace")
{
	$plugins->run_hooks("admin_style_templates_search_replace");
	
	if($mybb->request_method == "post")
	{
		if($mybb->input['type'] == "templates")
		{
			// Search and replace in templates
			
			if(!$mybb->input['find'])
			{
				flash_message($lang->search_noneset, "error");
				admin_redirect("index.php?module=style/templates&action=search_replace");
			}
			else
			{				
				$page->output_header($lang->search_replace);
	
				$page->output_nav_tabs($sub_tabs, 'search_replace');
					
				$templates_list = array();
				$table = new Table;
				
				$template_sets = array();
				
				// Get the names of all template sets
				$template_sets[-2] = $lang->master_templates;
				$template_sets[-1] = $lang->global_templates;
				
				$query = $db->simple_select("templatesets", "sid, title");
				while($set = $db->fetch_array($query))
				{
					$template_sets[$set['sid']] = $set['title'];
				}
				
				// Select all templates with that search term
				$query = $db->simple_select("templates", "tid, title, template, sid", "template LIKE '%".$db->escape_string($mybb->input['find'])."%'", array('order_by' => 'sid, title', 'order_dir' => 'ASC'));
				if($db->num_rows($query) == 0)
				{
					$table->construct_cell($lang->sprintf($lang->search_noresults, htmlspecialchars_uni($mybb->input['find'])), array("class" => "align_center"));
							
					$table->construct_row();
					
					$table->output($lang->search_results);
				}
				else
				{
					while($template = $db->fetch_array($query))
					{
						$template_list[$template['sid']][$template['title']] = $template;
					}
		
					$count = 0;
					
					foreach($template_list as $sid => $templates)
					{
						++$count;
						
						$search_header = $lang->sprintf($lang->search_header, htmlspecialchars_uni($mybb->input['find']), $template_sets[$sid]);						
						$table->construct_header($search_header, array("colspan" => 2));
		
						foreach($templates as $title => $template)
						{
							// Do replacement
							$newtemplate = str_replace($mybb->input['find'], $mybb->input['replace'], $template['template']);
							if($newtemplate != $template['template'])
							{
								// If the template is different, that means the search term has been found.
								if(trim($mybb->input['replace']) != "")
								{
									if($template['sid'] == -2)
									{
										// The template is a master template.  We have to make a new custom template.
										$new_template = array(
											"title" => $db->escape_string($title),
											"template" => $db->escape_string($newtemplate),
											"sid" => 1,
											"version" => $mybb->version_code,
											"status" => '',
											"dateline" => TIME_NOW
										);
										$new_tid = $db->insert_query("templates", $new_template);
										$label = $lang->sprintf($lang->search_created_custom, $template['title']);
										$url = "index.php?module=style/templates&amp;action=edit_template&amp;tid={$new_tid}&amp;sid=1";
									}
									else
									{
										// The template is a custom template.  Replace as normal.
										// Update the template if there is a replacement term
										$updatedtemplate = array(
											"template" => $db->escape_string($newtemplate)
										);
										$db->update_query("templates", $updatedtemplate, "tid='".$template['tid']."'");
										$label = $lang->sprintf($lang->search_updated, $template['title']);
										$url = "index.php?module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid={$template['sid']}";
									}
								}
								else
								{
									// Just show that the term was found
									if($template['sid'] == -2)
									{
										$label = $lang->sprintf($lang->search_found, $template['title']);
									}
									else
									{
										$label = $lang->sprintf($lang->search_found, $template['title']);
										$url = "index.php?module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid={$template['sid']}";
									}
								}
							}
						
							$table->construct_cell($label, array("width" => "85%"));
							
							if($sid == -2)
							{
								$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
		
								foreach($template_sets as $set_sid => $title)
								{
									if($set_sid > 0)
									{									
										$popup->add_item($lang->edit_in." ".htmlspecialchars_uni($title), "index.php?module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid={$set_sid}");
									}
								}
								
								$table->construct_cell($popup->fetch(), array("class" => "align_center"));
							}
							else
							{
								$table->construct_cell("<a href=\"{$url}\">Edit</a>", array("class" => "align_center"));
							}
							
							$table->construct_row();
						}
						
						if($count == 1)
						{
							$table->output($lang->search_results);
						}
						else
						{
							$table->output();
						}
					}
				}
				
				if(trim($mybb->input['replace']) != "")
				{
					// Log admin action - only if replace
					log_admin_action($mybb->input['find'], $mybb->input['replace']);
				}
				
				$page->output_footer();
				exit;
			}
		}
		else
		{
			if(!$mybb->input['title'])
			{
				flash_message($lang->search_noneset, "error");
				admin_redirect("index.php?module=style/templates&action=search_replace");
			}
			else
			{
				// Search Template Titles
				$page->output_header($lang->search_replace);
	
				$page->output_nav_tabs($sub_tabs, 'search_replace');
				
				$templatessets = array();
				
				$templates_sets = array();
				// Get the names of all template sets
				$template_sets[-2] = $lang->master_templates;
				$template_sets[-1] = $lang->global_templates;
				
				$query = $db->simple_select("templatesets", "sid, title");
				while($set = $db->fetch_array($query))
				{
					$template_sets[$set['sid']] = $set['title'];
				}
				
				$table = new Table;
				
				$query = $db->query("
					SELECT t.tid, t.title, t.sid, s.title as settitle, t2.tid as customtid
					FROM ".TABLE_PREFIX."templates t
					LEFT JOIN ".TABLE_PREFIX."templatesets s ON (t.sid=s.sid)
					LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t.title=t2.title AND t2.sid='1')
					WHERE t.title LIKE '%".$db->escape_string($mybb->input['title'])."%'
					ORDER BY t.title ASC
				");
				while($template = $db->fetch_array($query))
				{
					if($template['sid'] == -2)
					{
						if(!$template['customtid'])
						{
							$template['original'] = true;
						}
						else
						{
							$template['modified'] = true;
						}
					}
					else
					{
						$template['original'] = false;
						$template['modified'] = false;
					}
					$templatessets[$template['sid']][$template['title']] = $template;
				}
				
				$count = 0;
				
				foreach($templatessets as $sid => $templates)
				{
					++$count;
					
					$table->construct_header($template_sets[$sid], array("colspan" => 2));
					
					foreach($templates as $template)
					{
						$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
						
						if($sid == -2)
						{
							foreach($template_sets as $set_sid => $title)
							{
								if($set_sid < 0) continue;
								
								$popup->add_item($lang->edit_in." ".htmlspecialchars_uni($title), "index.php?module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid={$set_sid}");
							}
						}
						else
						{
							$popup->add_item($lang->full_edit, "index.php?module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid={$sid}");
						}
						
						if(isset($template['modified']) && $template['modified'] == true)
						{					
							if($sid > 0)
							{
								$popup->add_item($lang->diff_report, "index.php?module=style/templates&amp;action=diff_report&amp;title=".urlencode($template['title'])."&amp;sid2={$sid}");
							
								$popup->add_item($lang->revert_to_orig, "index.php?module=style/templates&amp;action=revert&amp;tid={$template['tid']}&amp;sid={$sid}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_revertion}')");
							}
							
							$template['title'] = "<span style=\"color: green;\">{$template['title']}</span>";
						}				
						// This template does not exist in the master list
						else if(!isset($template['original']) || $template['original'] == false)
						{
							$popup->add_item($lang->delete_template, "index.php?module=style/templates&amp;action=delete_template&amp;tid={$template['tid']}&amp;sid={$sid}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_deletion}')");
							
							$template['title'] = "<span style=\"color: blue;\">{$template['title']}</span>";
						}
											
						$table->construct_cell("<span style=\"padding: 20px;\"><a href=\"index.php?module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid={$sid}\">{$template['title']}</a></span>", array("width" => "85%")); // onclick=\"Templates.quick_edit('{$template['tid']}'); return false;\"
						$table->construct_cell($popup->fetch(), array("class" => "align_center"));
						
						$table->construct_row();
					}
					
					if($count == 1)
					{
						$table->output($lang->sprintf($lang->search_names_header, htmlspecialchars_uni($mybb->input['title'])));
					}
					else
					{
						$table->output();
					}
				}
				
				$page->output_footer();
				exit;
			}
		}
	}
	
	$page->extra_header .= '
	<link type="text/css" href="./jscripts/codepress/languages/codepress-php.css" rel="stylesheet" id="cp-lang-style" />
	<script type="text/javascript" src="./jscripts/codepress/codepress.js"></script>
	<script type="text/javascript">
		CodePress.language = \'php\';
	</script>';
	
	$page->output_header($lang->search_replace);
	
	$page->output_nav_tabs($sub_tabs, 'search_replace');
	
	$form = new Form("index.php?module=style/templates&amp;action=search_replace", "post", "do_template");
	echo $form->generate_hidden_field('type', "templates");
		
	$form_container = new FormContainer($lang->search_replace);
	$form_container->output_row($lang->search_for, "", $form->generate_text_area('find', $mybb->input['find'], array('id' => 'find', 'class' => 'codepress mybb', 'style' => 'width: 100%; height: 200px;')));
	
	$form_container->output_row($lang->replace_with, "", $form->generate_text_area('replace', $mybb->input['replace'], array('id' => 'replace', 'class' => 'codepress mybb', 'style' => 'width: 100%; height: 200px;')));
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->find_and_replace);

	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	echo "<br />";

	
	$form = new Form("index.php?module=style/templates&amp;action=search_replace", "post", "do_title");
	echo $form->generate_hidden_field('type', "titles");
		
	$form_container = new FormContainer($lang->search_template_names);
	
	$form_container->output_row($lang->search_for, "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	
	$form_container->end();
	
	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->find_templates);
	$buttons[] = $form->generate_reset_button($lang->reset);

	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	echo "<script language=\"Javascript\" type=\"text/javascript\">
	Event.observe('do_template', 'submit', function()
	{
		if($('find_cp')) {
			var area = $('find_cp');
			area.id = 'find';
			area.value = find.getCode();
			area.disabled = false;
		}
		
		if($('replace_cp')) {
			var area = $('replace_cp');
			area.id = 'replace';
			area.value = replace.getCode();
			area.disabled = false;
		}
	});
</script>";

	$page->output_footer();
}

if($mybb->input['action'] == "find_updated")
{
	$plugins->run_hooks("admin_style_templates_find_updated");

	// Finds templates that are old and have been updated by MyBB
	$compare_version = $mybb->version_code;
	$query = $db->query("
		SELECT COUNT(*) AS updated_count
		FROM ".TABLE_PREFIX."templates t 
		LEFT JOIN ".TABLE_PREFIX."templates m ON (m.title=t.title AND m.sid=-2 AND m.version > t.version)
		WHERE t.sid > 0 AND m.template != t.template
	");
	$count = $db->fetch_array($query);

	if($count['updated_count'] < 1)
	{
		flash_message($lang->no_updated_templates, 'success');
		admin_redirect("index.php?module=style/templates");
	}
	
	$page->output_header($lang->find_updated);
	
	$page->output_nav_tabs($sub_tabs, 'find_updated');

	$query = $db->simple_select("templatesets", "*", "", array('order_by' => 'title'));
	while($templateset = $db->fetch_array($query))
	{
		$templatesets[$templateset['sid']] = $templateset;
	}
	
	
	echo <<<LEGEND
	<fieldset>
<legend>{$lang->legend}</legend>
<ul>
<li>{$lang->updated_template_welcome1}</li>
<li>{$lang->updated_template_welcome2}</li>
<li>{$lang->updated_template_welcome3}</li>
</ul>
</fieldset>
LEGEND;
	
	$count = 0;
	$done_set = array();
	$done_output = array();
	$templates = array();
	$table = new Table;	
	
	$query = $db->query("
		SELECT t.tid, t.title, t.sid, t.version 
		FROM ".TABLE_PREFIX."templates t 
		LEFT JOIN ".TABLE_PREFIX."templates m ON (m.title=t.title AND m.sid=-2 AND m.version > t.version)
		WHERE t.sid > 0 AND m.template != t.template
		ORDER BY t.sid ASC, title ASC
	");
	while($template = $db->fetch_array($query))
	{
		$templates[$template['sid']][] = $template;
	}
	
	foreach($templates as $sid => $templates)
	{
		if(!$done_set[$sid])
		{
			$table->construct_header($templatesets[$sid]['title'], array("colspan" => 2));
			
			$done_set[$sid] = 1;
			++$count;
		}
		
		foreach($templates as $template)
		{		
			$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
			//$popup->add_item($lang->inline_edit, "javascript:;");
			$popup->add_item($lang->full_edit, "index.php?module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid=".$sid);
			$popup->add_item($lang->diff_report, "index.php?module=style/templates&amp;action=diff_report&amp;title=".urlencode($template['title'])."&amp;sid1=".$template['sid']."&amp;sid2=-2");
			$popup->add_item($lang->revert_to_orig, "index.php?module=style/templates&amp;action=revert&amp;tid={$template['tid']}&amp;sid={$sid}&amp;find_updated=1&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_revertion}')");
				
			$table->construct_cell("<a href=\"index.php?module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid={$sid}\">{$template['title']}</a>", array('width' => '80%')); // onclick=\"Templates.quick_edit('{$template['tid']}'); return false;\"
			$table->construct_cell($popup->fetch(), array("class" => "align_center"));
			
			$table->construct_row();
		}
		
		if($done_set[$sid] && !$done_output[$sid])
		{		
			$done_output[$sid] = 1;
			if($count == 1)
			{
				$table->output($lang->find_updated);
			}
			else
			{
				$table->output();
			}
		}
	}
	
	$page->output_footer();
}

if($mybb->input['action'] == "delete_set")
{
	$plugins->run_hooks("admin_style_templates_delete_set");
	
	$query = $db->simple_select("templatesets", "*", "sid='{$sid}' AND sid > 0");
	$set = $db->fetch_array($query);
	
	// Does the template not exist?
	if(!$set['sid'])
	{
		flash_message($lang->error_invalid_template_set, 'error');
		admin_redirect("index.php?module=style/templates");
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=style/templates");
	}

	if($mybb->request_method == "post")
	{
		// Delete the template
		$db->delete_query("templatesets", "sid='{$set['sid']}'");
		
		$plugins->run_hooks("admin_style_templates_delete_set_commit");

		// Log admin action
		log_admin_action($set['sid'], $set['title']);

		flash_message($lang->success_template_set_deleted, 'success');
		admin_redirect("index.php?module=style/templates");
	}
	else
	{		
		$page->output_confirm_action("index.php?module=style/templates&amp;action=delete_set&amp;sid={$set['sid']}", $lang->confirm_template_set_deletion);
	}
	
}

if($mybb->input['action'] == "delete_template")
{
	$plugins->run_hooks("admin_style_templates_delete_template");
	
	$query = $db->query("
		SELECT t.*, s.title as set_title
		FROM ".TABLE_PREFIX."templates t
		LEFT JOIN ".TABLE_PREFIX."templatesets s ON(t.sid=s.sid)
		WHERE t.tid='".intval($mybb->input['tid'])."' AND t.sid > '-2' AND t.sid = '{$sid}'
	");
	$template = $db->fetch_array($query);
	
	// Does the template not exist?
	if(!$template)
	{
		flash_message($lang->error_invalid_template, 'error');
		admin_redirect("index.php?module=style/templates");
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=style/templates&sid={$template['sid']}{$expand_str2}");
	}

	if($mybb->request_method == "post")
	{
		// Delete the template
		$db->delete_query("templates", "tid='{$template['tid']}'");
		
		$plugins->run_hooks("admin_style_templates_delete_template_commit");

		// Log admin action
		log_admin_action($template['tid'], $template['title'], $template['sid'], $template['set_title']);

		flash_message($lang->success_template_deleted, 'success');
		admin_redirect("index.php?module=style/templates&sid={$template['sid']}{$expand_str2}");
	}
	else
	{		
		$page->output_confirm_action("index.php?module=style/templates&amp;action=delete_template&amp;sid={$template['sid']}{$expand_str}", $lang->confirm_template_deletion);
	}
}

if($mybb->input['action'] == "diff_report")
{
	// Compares a template of sid1 with that of sid2, if no sid1, it is assumed -2
	if(!$mybb->input['sid1'])
	{
		$mybb->input['sid1'] = -2;
	}
	
	if($mybb->input['sid2'] == -2)
	{
		$sub_tabs['find_updated'] = array(
			'title' => $lang->find_updated,
			'link' => "index.php?module=style/templates&amp;action=find_updated"
		);
	}
	
	$sub_tabs['diff_report'] = array(
		'title' => $lang->diff_report,
		'link' => "index.php?module=style/templates&amp;action=diff_report&amp;title=".$db->escape_string($mybb->input['title'])."&amp;sid1=".intval($mybb->input['sid1'])."&amp;sid2=".intval($mybb->input['sid2']),
		'description' => $lang->diff_report_desc
	);
	
	$plugins->run_hooks("admin_style_templates_diff_report");
	
	$query = $db->simple_select("templates", "*", "title='".$db->escape_string($mybb->input['title'])."' AND sid='".intval($mybb->input['sid1'])."'");
	$template1 = $db->fetch_array($query);

	$query = $db->simple_select("templates", "*", "title='".$db->escape_string($mybb->input['title'])."' AND sid='".intval($mybb->input['sid2'])."'");
	$template2 = $db->fetch_array($query);

	if($template1['template'] == $template2['template'])
	{
		flash_message($lang->templates_the_same, 'error');
		admin_redirect("index.php?module=style/templates&sid=".intval($mybb->input['sid2']).$expand_str);
	}

	$template1['template'] = explode("\n", $template1['template']);
	$template2['template'] = explode("\n", $template2['template']);

	$plugins->run_hooks("admin_style_templates_diff_report_run");
	require_once MYBB_ROOT."inc/3rdparty/diff/Diff.php";	
	require_once MYBB_ROOT."inc/3rdparty/diff/Diff/Renderer/inline.php";

	$diff = &new Text_Diff('auto', array($template1['template'], $template2['template']));
	$renderer = &new Text_Diff_Renderer_inline();
	
	if($sid)
	{
		$page->add_breadcrumb_item($template_sets[$sid], "index.php?module=style/templates&amp;sid={$sid}{$expand_str}");
	}
	
	$page->output_header($lang->template_sets);
	
	$page->output_nav_tabs($sub_tabs, 'diff_report');
	
	$table = new Table;
	
	$table->construct_header("<ins>".$lang->master_updated_ins."</ins><br /><del>".$lang->master_updated_del."</del>");
	
	$table->construct_cell("<pre>".$renderer->render($diff)."</pre>");
	$table->construct_row();
	
	$table->output($lang->template_diff_analysis);
	
	$page->output_footer();
}

if($mybb->input['action'] == "revert")
{
	$plugins->run_hooks("admin_style_templates_revert");
	
	$query = $db->query("
		SELECT t.*, s.title as set_title
		FROM ".TABLE_PREFIX."templates t
		LEFT JOIN ".TABLE_PREFIX."templatesets s ON(s.sid=t.sid)
		WHERE t.tid='".intval($mybb->input['tid'])."' AND t.sid > 0 AND t.sid = '".intval($mybb->input['sid'])."'
	");
	$template = $db->fetch_array($query);
	
	// Does the template not exist?
	if(!$template)
	{
		flash_message($lang->error_invalid_template, 'error');
		admin_redirect("index.php?module=style/templates");
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=style/templates&sid={$template['sid']}{$expand_str2}");
	}

	if($mybb->request_method == "post")
	{
		// Revert the template
		$db->delete_query("templates", "tid='{$template['tid']}'");
		
		$plugins->run_hooks("admin_style_templates_revert_commit");

		// Log admin action
		log_admin_action($template['tid'], $template['sid'], $template['sid'], $template['set_title']);

		flash_message($lang->success_template_reverted, 'success');
		
		if($mybb->input['find_updated'] == 1)
		{
			admin_redirect("index.php?module=style/templates&action=find_updated");
		}
		else
		{
			admin_redirect("index.php?module=style/templates&sid={$template['sid']}{$expand_str2}");
		}
	}
	else
	{	
		$page->output_confirm_action("index.php?module=style/templates&amp;sid={$template['sid']}{$expand_str}", $lang->confirm_template_revertion);
	}
}

if($mybb->input['sid'] && !$mybb->input['action'])
{
	$plugins->run_hooks("admin_style_templates_set");
	
	$table = new Table;
	
	$page->add_breadcrumb_item($template_sets[$sid], "index.php?module=style/templates&amp;sid={$sid}");
	
	/*$page->extra_header .= '
	<link type="text/css" href="./jscripts/codepress/languages/codepress-php.css" rel="stylesheet" id="cp-lang-style" />
	<script type="text/javascript" src="./jscripts/codepress/codepress.js"></script>
	<script type="text/javascript">
		CodePress.language = \'php\';
		var my_post_key = \''.$mybb->post_code.'\';
	</script>';
	$page->extra_header .= "<script src=\"../jscripts/scriptaculous.js?load=effects\" type=\"text/javascript\"></script>\n";
	$page->extra_header .= "<script src=\"./jscripts/templates.js\" type=\"text/javascript\"></script>\n";*/

	$page->output_header($lang->template_sets);
	
	$page->output_nav_tabs($sub_tabs, 'manage_templates');
	
	$table->construct_header($lang->template_set);
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));
	
	// Global Templates
	if($sid == -1)
	{
		$query = $db->simple_select("templates", "tid,title", "sid='-1'", array('order_by' => 'title', 'order_dir' => 'ASC'));
		while($template = $db->fetch_array($query))
		{
			$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
			//$popup->add_item($lang->inline_edit, "javascript:;");
			$popup->add_item($lang->full_edit, "index.php?module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid=-1");
			$popup->add_item($lang->delete_template, "index.php?module=style/templates&amp;action=delete_template&amp;tid={$template['tid']}&amp;sid=-1&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_deletion}')");
				
			$table->construct_cell("<a href=\"index.php?module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid=-1\">{$template['title']}</a>"); // onclick=\"Templates.quick_edit('{$template['tid']}'); return false;\"
			$table->construct_cell($popup->fetch(), array("class" => "align_center"));
			
			$table->construct_row();
		}
		
		if($table->num_rows() == 0)
		{
			$table->construct_cell($lang->no_global_templates, array('colspan' => 2));
			$table->construct_row();
		}
		
		$table->output($template_sets[$sid]);
	
		$page->output_footer();
	}
	
	if($mybb->input['expand'] == 'all')
	{
		// if we're expanding everything, stick in the ungrouped templates in the list as well
		$expand_array = array(-1);
	}
	// Fetch Groups
	$template_sql = '';
	$query = $db->simple_select("templategroups", "*");
	while($templategroup = $db->fetch_array($query))
	{
		$templategroup['title'] = $lang->parse($templategroup['title'])." ".$lang->templates;
		if($mybb->input['expand'] == 'all')
		{
			$expand_array[] = $templategroup['gid'];
		}
		if(in_array($templategroup['gid'], $expand_array))
		{
			$templategroup['expanded'] = 1;
			$template_sql .= " OR title LIKE '{$templategroup['prefix']}%'";
		}
		$template_groups[$templategroup['prefix']] = $templategroup;
	}
	
	function sort_template_groups($a, $b)
	{
		return strcasecmp($a['title'], $b['title']);
	}
	uasort($template_groups, "sort_template_groups");
	
	// Add the ungrouped templates group at the bottom
	$template_groups['-1'] = array(
		"prefix" => "",
		"title" => $lang->ungrouped_templates,
		"gid" => -1
	);
	
	if(count($expand_array) > 0)
	{
		// The ungrouped list is expanded so we need to load all templates
		if(in_array('-1', $expand_array))
		{
			$template_sql = '';
		}
		else
		{
			$template_sql = " AND (1=0{$template_sql})";
		}
					
		// Load the list of templates
		$query = $db->simple_select("templates", "*", "(sid='".intval($mybb->input['sid'])."' OR sid='-2') {$template_sql}", array('order_by' => 'sid DESC, title', 'order_dir' => 'ASC'));
		while($template = $db->fetch_array($query))
		{
			$exploded = explode("_", $template['title'], 2);
			
			if(isset($template_groups[$exploded[0]]))
			{
				$group = $exploded[0];
			}
			else
			{
				$group = -1;
			}
			
			// If this template is not a master template, we simple add it to the list
			if($template['sid'] != -2)
			{
				$template['original'] = false;
				$template['modified'] = false;
				$template_groups[$group]['templates'][$template['title']] = $template;
			}
			// Otherwise, if we are down to master templates we need to do a few extra things
			else
			{				
				// Master template that hasn't been customised in the set we have expanded
				if(!isset($template_groups[$group]['templates'][$template['title']]) || $template_groups[$group]['templates'][$template['title']]['template'] == $template['template'])
				{
					$template['original'] = true;
					$template_groups[$group]['templates'][$template['title']] = $template;
				}
				// Template has been modified in the set we have expanded (it doesn't match the master)
				else if($template_groups[$group]['templates'][$template['title']]['template'] != $template['template'] && $template_groups[$group]['templates'][$template['title']]['sid'] != -2)
				{
					$template_groups[$group]['templates'][$template['title']]['modified'] = true;
				}
				
				// Save some memory!
				unset($template_groups[$group]['templates'][$template['title']]['template']);
			}
		}
	}
	
	foreach($template_groups as $prefix => $group)
	{	
		$tmp_expand = "";
		if(in_array($group['gid'], $expand_array))
		{
			$expand = $lang->collapse;
			$expanded = true;
			
			$tmp_expand = $expand_array;
			$unsetgid = array_search($group['gid'], $tmp_expand);
			unset($tmp_expand[$unsetgid]);
			$group['expand_str'] = implode("|", $tmp_expand);
		}
		else
		{
			$expand = $lang->expand;
			$expanded = false;
			
			$group['expand_str'] = implode("|", $expand_array);
			if($group['expand_str'])
			{
				$group['expand_str'] .= "|";
			}
			$group['expand_str'] .= $group['gid'];
		}
		
		$table->construct_cell("<strong><a href=\"index.php?module=style/templates&amp;sid={$sid}&amp;expand={$group['expand_str']}\">{$group['title']}</a></strong>");
		$table->construct_cell("<a href=\"index.php?module=style/templates&amp;sid={$sid}&amp;expand={$group['expand_str']}#group_{$group['gid']}\">{$expand}</a>", array("class" => "align_center"));
		$table->construct_row(array("class" => "alt_row", "id" => "group_".$group['gid'], "name" => "group_".$group['gid']));
		
		if($expanded == true && isset($group['templates']) && count($group['templates']) > 0)
		{
			$templates = $group['templates'];
			ksort($templates);
			
			foreach($templates as $template)
			{
				$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
				//$popup->add_item($lang->inline_edit, "javascript:;", "Templates.quick_edit('{$template['tid']}');");
				$popup->add_item($lang->full_edit, "index.php?module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid={$sid}{$expand_str}");
				
				if(isset($template['modified']) && $template['modified'] == true)
				{					
					if($sid > 0)
					{
						$popup->add_item($lang->diff_report, "index.php?module=style/templates&amp;action=diff_report&amp;title=".urlencode($template['title'])."&amp;sid2={$sid}");
					
						$popup->add_item($lang->revert_to_orig, "index.php?module=style/templates&amp;action=revert&amp;tid={$template['tid']}&amp;sid={$sid}&amp;my_post_key={$mybb->post_code}{$expand_str}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_revertion}')");
					}
					
					$template['title'] = "<span style=\"color: green;\">{$template['title']}</span>";
				}				
				// This template does not exist in the master list
				else if(isset($template['original']) && $template['original'] == false)
				{
					$popup->add_item($lang->delete_template, "index.php?module=style/templates&amp;action=delete_template&amp;tid={$template['tid']}&amp;sid={$sid}&amp;my_post_key={$mybb->post_code}{$expand_str}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_deletion}')");
					
					$template['title'] = "<span style=\"color: blue;\">{$template['title']}</span>";
				}
									
				$table->construct_cell("<span style=\"padding: 20px;\"><a href=\"index.php?module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid={$sid}{$expand_str}\" >{$template['title']}</a></span>"); // onclick=\"Templates.quick_edit('{$template['tid']}'); return false;\"
				$table->construct_cell($popup->fetch(), array("class" => "align_center"));
				
				$table->construct_row();
			}
		}
	}
	
	$table->output($template_sets[$sid]);
	
	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_style_templates_start");
	
	$page->output_header($lang->template_sets);
	
	$page->output_nav_tabs($sub_tabs, 'templates');
	
	$themes = array();
	$query = $db->simple_select("themes", "name,tid,properties", "tid != '1'");
	while($theme = $db->fetch_array($query))
	{
		$tbits = unserialize($theme['properties']);
		$themes[$tbits['templateset']][$theme['tid']] = $theme['name'];
	}
	
	$template_sets = array();
	$template_sets[-1]['title'] = $lang->global_templates;
	$template_sets[-1]['sid'] = -1;

	$query = $db->simple_select("templatesets", "*", "", array('order_by' => 'title', 'order_dir' => 'ASC'));
	while($template_set = $db->fetch_array($query))
	{
		$template_sets[$template_set['sid']] = $template_set;
	}
	
	$table = new Table;
	$table->construct_header($lang->template_set);
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));
	
	foreach($template_sets as $set)
	{
		if($set['sid'] == -1)
		{
			$table->construct_cell("<strong><a href=\"index.php?module=style/templates&amp;sid=-1\">{$lang->global_templates}</a></strong><br /><small>{$lang->used_by_all_themes}</small>");
			$table->construct_cell("<a href=\"index.php?module=style/templates&amp;sid=-1\">{$lang->expand_templates}</a>", array("class" => "align_center"));
			$table->construct_row();
			continue;
		}
		
		if($themes[$set['sid']])
		{
			$used_by_note = $lang->used_by;
			$comma = "";
			foreach($themes[$set['sid']] as $theme_name)
			{
				$used_by_note .= $comma.$theme_name;
				$comma = ", ";
			}
		}
		else
		{
			$used_by_note = $lang->not_used_by_any_themes;
		}
		
		if($set['sid'] == 1)
		{
			$actions = "<a href=\"index.php?module=style/templates&amp;sid={$set['sid']}\">{$lang->expand_templates}</a>";
		}
		else
		{	
			$popup = new PopupMenu("templateset_{$set['sid']}", $lang->options);
			$popup->add_item($lang->expand_templates, "index.php?module=style/templates&amp;sid={$set['sid']}");		
			
			if($set['sid'] != 1)
			{
				$popup->add_item($lang->edit_template_set, "index.php?module=style/templates&amp;action=edit_set&amp;sid={$set['sid']}");
					
				if(!$themes[$set['sid']])
				{
					$popup->add_item($lang->delete_template_set, "index.php?module=style/templates&amp;action=delete_set&amp;sid={$set['sid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_set_deletion}')");
				}
			}
			
			$actions = $popup->fetch();
		}
		
		$table->construct_cell("<strong><a href=\"index.php?module=style/templates&amp;sid={$set['sid']}\">{$set['title']}</a></strong><br /><small>{$used_by_note}</small>");
		$table->construct_cell($actions, array("class" => "align_center"));
		$table->construct_row();
	}
	
	$table->output($lang->template_sets);

	$page->output_footer();
}

?>