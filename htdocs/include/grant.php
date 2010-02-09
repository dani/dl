<?php
// process a grant

// try to fetch the grant
$id = $_REQUEST["g"];
$ref = "$masterPath?g=$id";

$sql = "SELECT * FROM grant WHERE id = " . $db->quote($id);
$GRANT = $db->query($sql)->fetch();
if($GRANT === false)
{
  includeTemplate("style/include/nogrant.php",
      array('title' => T_("Unknown grant"), 'id' => htmlentities($id)));
  exit();
}

if(isset($GRANT['pass_md5']) && !isset($_SESSION['g'][$id]))
{
  $pass = (empty($_REQUEST["p"])? false: md5($_REQUEST["p"]));
  if($pass === $GRANT['pass_md5'])
  {
    // authorize the grant for this session
    $_SESSION['g'][$id] = $pass;
  }
  else
  {
    include("include/grantp.php");
    exit();
  }
}


// upload handler
function failUpload($file)
{
  unlink($file);
  return false;
}

function handleUpload($GRANT, $FILE)
{
  global $dataDir, $db;

  // generate new unique id/file name
  list($id, $tmpFile) = genTicketId($FILE["name"]);
  if(!move_uploaded_file($FILE["tmp_name"], $tmpFile))
    return failUpload($tmpFile);

  // convert the upload to a ticket
  $db->beginTransaction();

  $sql = "INSERT INTO ticket (id, user_id, name, path, size, cmt, pass_md5"
    . ", time, last_time, expire, expire_dln) VALUES (";
  $sql .= $db->quote($id);
  $sql .= ", " . $GRANT['user_id'];
  $sql .= ", " . $db->quote(basename($FILE["name"]));
  $sql .= ", " . $db->quote($tmpFile);
  $sql .= ", " . $FILE["size"];
  $sql .= ", " . (empty($GRANT["cmt"])? 'NULL': $db->quote($GRANT["cmt"]));
  $sql .= ", " . (empty($GRANT["pass_md5"])? 'NULL': $db->quote($GRANT["pass_md5"]));
  $sql .= ", " . time();
  $sql .= ", " . (empty($GRANT["last_time"])? 'NULL': $GRANT['last_time']);
  $sql .= ", " . (empty($GRANT["expire"])? 'NULL': $GRANT['expire']);
  $sql .= ", " . (empty($GRANT["expire_dln"])? 'NULL': $GRANT['expire_dln']);
  $sql .= ")";
  $db->exec($sql);

  $sql = "DELETE FROM grant WHERE id = " . $db->quote($GRANT['id']);
  $db->exec($sql);

  if(!$db->commit())
    return failUpload($tmpFile);

  // fetch defaults
  $sql = "SELECT * FROM ticket WHERE id = " . $db->quote($id);
  $DATA = $db->query($sql)->fetch();

  // trigger use hooks
  onGrantUse($GRANT, $DATA);

  return $DATA;
}


// handle the request
$DATA = false;
if(isset($_FILES["file"])
&& is_uploaded_file($_FILES["file"]["tmp_name"])
&& $_FILES["file"]["error"] == UPLOAD_ERR_OK)
  $DATA = handleUpload($GRANT, $_FILES["file"]);

// resulting page
if($DATA === false)
  include("include/grants.php");
else
{
  unset($ref);
  includeTemplate("style/include/grantr.php");
}

?>