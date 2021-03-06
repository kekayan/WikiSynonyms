<?php
$non = array();

$db_skills = Application::getAllOdeskSkills();
$db_skills_check = array();
foreach ($db_skills as $k => $db_skill) {
  $db_skills_check[] = $db_skill['skill'];
}

$od_skills = Helper::curl_get_result('http://www.odesk.com/api/profiles/v1/metadata/skills.json');
$odesk_skills = json_decode($od_skills);
$skills = $odesk_skills->skills;

foreach ($skills as $k => $skill) {
  if (in_array($skill, $db_skills_check)) {
    continue;
  } else {
    $skill_data = json_decode(Helper::curl_get_result('http://www.odesk.com/api/profiles/v1/metadata/skills/' . $skill . '.json'));
    if ($skill_data->skill->external_link) {
      $non[] = $skill_data->skill;
    }
  }
}

//echo 'New: '.count($non).'<br/>';

if (count($non) > 0) {
  $fp = fopen(dirname(dirname(__FILE__)) . '/public/assets/produced/skills_to_add.json', 'w');
  fwrite($fp, json_encode($non));
  fclose($fp);

  $query = 'INSERT INTO odesk_skills (skill, pretty_name, external_link, description, wikipedia_page_id) VALUES ';
  foreach ($non as $new_skill) {
    $query .= sprintf("('%s', '%s', '%s', '%s', %s), ", 
      mysql_real_escape_string($new_skill->skill), 
      mysql_real_escape_string($new_skill->skill), 
      mysql_real_escape_string($new_skill->external_link), 
      mysql_real_escape_string($new_skill->description), 
      0
      );
  }
  try {
    Application::doConnect();
    mysql_query(rtrim($query, ', '));
    Application::doClose();
  } catch (Exception $exc) {
    throw new Exception($exc->getMessage());
  }
}
$wiki_skills = Application::getAllOdeskSkillsWithExternalLink();

$fp = fopen(dirname(dirname(__FILE__)) . '/public/assets/produced/file.csv', 'w');

foreach ($wiki_skills as $s => $skill) {
  if (preg_match('/http:\/\/en.wikipedia.org\/wiki\//', $skill['external_link'])) {
    $v = str_replace('http://en.wikipedia.org/wiki/', '', $skill['external_link']);
    $query = sprintf("SELECT stitle FROM page_relation WHERE ttitle = '%s'", str_replace('http://en.wikipedia.org/wiki/', '', $v));
    Application::doConnect();
    $result = mysql_query($query);
    while ($row = mysql_fetch_assoc($result)) {
      fputcsv($fp, array($skill['skill'], $v, $row['stitle']));
    }
    Application::doClose();
  }
}
fclose($fp);

$output_file = dirname(dirname(__FILE__)) . '/public/assets/produced/file.csv';
header("Content-Disposition: attachment; filename=\"" . basename($output_file) . "\"");
header("Content-Type: application/force-download");
header("Content-Length: " . filesize($output_file));
header("Connection: close");
die(readfile($output_file));